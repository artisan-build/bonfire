<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Events\MessagePosted;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Attachment;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

return new class extends Component
{
    use WithFileUploads;

    private const MAX_SCHEDULED_MESSAGES = 10;

    public Room $room;

    public ?int $parentId = null;

    public string $body = '';

    public ?string $scheduledFor = null;

    public ?string $lastScheduledAt = null;

    public ?int $lastScheduledMessageId = null;

    public bool $alsoSendToChannel = false;

    /** @var array<int, UploadedFile> */
    public array $pendingAttachments = [];

    /** @var array<int, int> Existing attachment ids to clone into the next message. */
    public array $pendingAttachmentIds = [];

    public string $pollQuestion = '';

    /** @var array<int, string> */
    public array $pollOptions = ['', ''];

    /** @var array{question: string, options: array<int, string>}|null */
    public ?array $pendingPoll = null;

    public function mount(Room $room, ?int $parentId = null): void
    {
        $this->room = $room;
        $this->parentId = $parentId;
    }

    #[Computed]
    public function member(): ?Member
    {
        return Bonfire::memberFor(auth()->user());
    }

    #[On('bonfire:member-updated')]
    public function onMemberUpdated(): void
    {
        unset($this->member, $this->mentionables);
    }

    /**
     * @return array<int, array{id: int, display_name: string, avatar_url: string}>
     */
    #[Computed]
    public function mentionables(): array
    {
        return Member::query()
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'avatar_url'])
            ->map(fn (Member $member): array => [
                'id' => $member->id,
                'display_name' => $member->display_name,
                'avatar_url' => $member->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($member->display_name),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Attachment>
     */
    #[Computed]
    public function recentAttachments(): Collection
    {
        $member = $this->member();
        if ($member === null) {
            return Attachment::query()->whereRaw('1=0')->get();
        }

        return Attachment::query()
            ->whereHas('message', fn ($q) => $q->where('member_id', $member->id))
            ->orderByDesc('id')
            ->limit(8)
            ->get();
    }

    public function attachExisting(int $attachmentId): void
    {
        $member = $this->member();
        if ($member === null) {
            return;
        }

        $exists = Attachment::query()
            ->whereKey($attachmentId)
            ->whereHas('message', fn ($q) => $q->where('member_id', $member->id))
            ->exists();

        if (! $exists) {
            return;
        }

        if (! in_array($attachmentId, $this->pendingAttachmentIds, true)) {
            $this->pendingAttachmentIds[] = $attachmentId;
        }
    }

    public function removePendingExisting(int $attachmentId): void
    {
        $this->pendingAttachmentIds = array_values(array_filter(
            $this->pendingAttachmentIds,
            fn ($id) => (int) $id !== $attachmentId,
        ));
    }

    public function removeAttachment(int $index): void
    {
        if (! array_key_exists($index, $this->pendingAttachments)) {
            return;
        }

        unset($this->pendingAttachments[$index]);
        $this->pendingAttachments = array_values($this->pendingAttachments);
    }

    public function send(): void
    {
        $body = trim($this->body);
        $plain = trim(strip_tags(html_entity_decode($body, ENT_QUOTES | ENT_HTML5)));

        if ($plain === ''
            && $this->pendingAttachments === []
            && $this->pendingAttachmentIds === []
            && $this->pendingPoll === null) {
            return;
        }

        $this->authorize('post', $this->room);

        $member = Bonfire::memberFor(auth()->user());
        abort_unless($member !== null && $member->is_active, 403);

        $parent = $this->resolveParent();

        // Staged poll (created via the Poll modal) — text body is whatever the
        // user typed alongside it.
        $poll = $this->pendingPoll;

        $effectiveBody = $plain === ''
            ? ($poll !== null ? (string) ($poll['question'] ?? '📎') : '📎')
            : $body;

        $scheduledAt = $this->scheduledFor !== null && $this->scheduledFor !== ''
            ? Carbon::parse($this->scheduledFor)
            : null;

        if ($scheduledAt !== null && $scheduledAt->isPast()) {
            $scheduledAt = null;
        }

        if ($scheduledAt !== null && $this->scheduledMessagesCount() >= self::MAX_SCHEDULED_MESSAGES) {
            Flux::toast(text: 'You can have at most '.self::MAX_SCHEDULED_MESSAGES.' scheduled messages. Send or cancel one first.');

            return;
        }

        $message = $this->createMessage($member, $parent, $effectiveBody, $scheduledAt, $poll);

        $this->storeAttachments($message);

        if ($parent !== null && $this->alsoSendToChannel && $scheduledAt === null) {
            Bonfire::postAs($member, $this->room, $effectiveBody, null);
        }

        if ($scheduledAt !== null) {
            $this->lastScheduledAt = $scheduledAt->toIso8601String();
            $this->lastScheduledMessageId = $message->getKey();
        }

        $this->pendingPoll = null;
        $this->reset('body', 'pendingAttachments', 'pendingAttachmentIds', 'scheduledFor', 'alsoSendToChannel');
        $this->dispatch('bonfire-own-message-sent');
    }

    public function dismissScheduled(): void
    {
        $this->releaseDueOwnedScheduledMessages();

        $this->lastScheduledAt = null;
        $this->lastScheduledMessageId = null;
    }

    /**
     * Convert any scheduled messages owned by the current member whose
     * scheduled_for has come due into regular posted messages, broadcasting
     * each so listeners refresh.
     */
    private function releaseDueOwnedScheduledMessages(): void
    {
        $member = $this->member();
        if ($member === null) {
            return;
        }

        $due = Message::query()
            ->where('member_id', $member->id)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->get();

        foreach ($due as $message) {
            $scheduledAt = $message->scheduled_for;
            $message->forceFill([
                'scheduled_for' => null,
                'created_at' => $scheduledAt ?? now(),
            ])->save();

            event(new MessagePosted($message));
        }

        if ($due->isNotEmpty()) {
            $this->dispatch('bonfire-scheduled-released');
        }
    }

    #[Computed]
    public function lastScheduledMessage(): ?Message
    {
        if ($this->lastScheduledMessageId === null) {
            return null;
        }

        return $this->findScheduledMessageForCurrentMember($this->lastScheduledMessageId);
    }

    /**
     * @return Collection<int, Message>
     */
    #[Computed]
    public function scheduledMessages(): Collection
    {
        $member = $this->member();
        if ($member === null) {
            /** @var Collection<int, Message> */
            return new Collection;
        }

        return Message::query()
            ->where('member_id', $member->id)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '>', now())
            ->orderBy('scheduled_for')
            ->limit(self::MAX_SCHEDULED_MESSAGES)
            ->get();
    }

    public function getMaxScheduledReachedProperty(): bool
    {
        return $this->scheduledMessagesCount() >= self::MAX_SCHEDULED_MESSAGES;
    }

    public function maxScheduled(): int
    {
        return self::MAX_SCHEDULED_MESSAGES;
    }

    public function updateScheduledMessage(int $id, string $body, string $newTime): void
    {
        $message = $this->findScheduledMessageForCurrentMember($id);
        if ($message === null) {
            return;
        }

        $newAt = Carbon::parse($newTime);
        if ($newAt->isPast()) {
            return;
        }

        $message->update([
            'body' => $body,
            'scheduled_for' => $newAt,
        ]);

        if ($id === $this->lastScheduledMessageId) {
            $this->lastScheduledAt = $newAt->toIso8601String();
        }

        unset($this->lastScheduledMessage, $this->scheduledMessages);
    }

    public function cancelScheduledMessage(int $id): void
    {
        $message = $this->findScheduledMessageForCurrentMember($id);
        if ($message === null) {
            return;
        }

        $message->delete();

        if ($id === $this->lastScheduledMessageId) {
            $this->lastScheduledAt = null;
            $this->lastScheduledMessageId = null;
        }

        unset($this->lastScheduledMessage, $this->scheduledMessages);
    }

    public function sendScheduledNow(int $id): void
    {
        $message = $this->findScheduledMessageForCurrentMember($id);
        if ($message === null) {
            return;
        }

        $message->update([
            'scheduled_for' => null,
            'created_at' => now(),
        ]);

        if ($id === $this->lastScheduledMessageId) {
            $this->lastScheduledAt = null;
            $this->lastScheduledMessageId = null;
        }

        unset($this->lastScheduledMessage, $this->scheduledMessages);
        $this->dispatch('bonfire-own-message-sent');
    }

    private function findScheduledMessageForCurrentMember(int $id): ?Message
    {
        $member = $this->member();
        if ($member === null) {
            return null;
        }

        return Message::query()
            ->where('id', $id)
            ->where('member_id', $member->id)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '>', now())
            ->first();
    }

    public function scheduledMessagesCount(): int
    {
        $member = $this->member();

        if ($member === null) {
            return 0;
        }

        return Message::query()
            ->where('member_id', $member->id)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '>', now())
            ->count();
    }

    /**
     * @param  array<string, mixed>|null  $poll
     */
    private function createMessage(Member $member, ?Message $parent, string $body, ?Carbon $scheduledAt, ?array $poll = null): Message
    {
        if ($scheduledAt === null && $poll === null) {
            return Bonfire::postAs($member, $this->room, $body, $parent);
        }

        $attrs = [
            'room_id' => $this->room->getKey(),
            'member_id' => $member->getKey(),
            'parent_id' => $parent?->getKey(),
            'body' => $body,
            'poll' => $poll,
            'scheduled_for' => $scheduledAt,
        ];

        if ($scheduledAt !== null) {
            return Message::withoutEvents(fn (): Message => Message::query()->create($attrs));
        }

        return Message::query()->create($attrs);
    }

    /**
     * Resolve the parent message for a reply, ensuring it belongs to this room.
     * A reply may only target a parent in the same room the composer is bound to.
     */
    private function resolveParent(): ?Message
    {
        if ($this->parentId === null) {
            return null;
        }

        $parent = Message::query()->findOrFail($this->parentId);

        abort_unless((int) $parent->room_id === (int) $this->room->getKey(), 403);

        return $parent;
    }

    public function addPollOption(): void
    {
        if (count($this->pollOptions) < 10) {
            $this->pollOptions[] = '';
        }
    }

    public function removePollOption(int $index): void
    {
        if (count($this->pollOptions) <= 2) {
            return;
        }

        unset($this->pollOptions[$index]);
        $this->pollOptions = array_values($this->pollOptions);
    }

    public function resetPollDraft(): void
    {
        $this->pollQuestion = '';
        $this->pollOptions = ['', ''];
        $this->resetErrorBag(['pollQuestion', 'pollOptions']);
    }

    /**
     * @return array{question: string, options: array<int, string>}|null
     */
    private function validatePollDraft(): ?array
    {
        $this->validate([
            'pollQuestion' => ['required', 'string', 'min:1', 'max:500'],
            'pollOptions' => ['array', 'min:2', 'max:10'],
            'pollOptions.*' => ['nullable', 'string', 'max:200'],
        ]);

        $options = array_values(array_filter(
            array_map(fn (string $o): string => trim($o), $this->pollOptions),
            fn (string $o): bool => $o !== '',
        ));

        if (count($options) < 2) {
            $this->addError('pollOptions', __('Add at least 2 non-empty options.'));

            return null;
        }

        return [
            'question' => trim($this->pollQuestion),
            'options' => array_slice($options, 0, 10),
        ];
    }

    public function createPoll(): void
    {
        $poll = $this->validatePollDraft();
        if ($poll === null) {
            return;
        }

        $this->authorize('post', $this->room);

        $member = Bonfire::memberFor(auth()->user());
        abort_unless($member !== null && $member->is_active, 403);

        $parent = $this->resolveParent();

        $bodyHtml = trim($this->body);
        $bodyPlain = trim(strip_tags(html_entity_decode($bodyHtml, ENT_QUOTES | ENT_HTML5)));
        $effectiveBody = $bodyPlain === '' ? $poll['question'] : $bodyHtml;

        $this->createMessage($member, $parent, $effectiveBody, null, $poll);

        if ($parent !== null && $this->alsoSendToChannel) {
            Bonfire::postAs($member, $this->room, $effectiveBody, null);
        }

        $this->reset('body');
        $this->pendingPoll = null;
        $this->resetPollDraft();
        $this->dispatch('modal-close', name: 'create-poll');
        $this->dispatch('bonfire-own-message-sent');
    }

    /**
     * Stage the poll into the draft so the user can keep typing before sending.
     */
    public function stagePoll(): void
    {
        $poll = $this->validatePollDraft();
        if ($poll === null) {
            return;
        }

        $this->pendingPoll = $poll;
        $this->resetPollDraft();
        $this->dispatch('modal-close', name: 'create-poll');
    }

    /**
     * Reopen the modal pre-filled with the staged poll for editing.
     */
    public function editPendingPoll(): void
    {
        if ($this->pendingPoll === null) {
            return;
        }

        $this->pollQuestion = (string) ($this->pendingPoll['question'] ?? '');
        $options = $this->pendingPoll['options'] ?? [];
        $this->pollOptions = count($options) >= 2 ? array_values($options) : ['', ''];

        $this->dispatch('modal-show', name: 'create-poll');
    }

    public function discardPendingPoll(): void
    {
        $this->pendingPoll = null;
    }

    private function storeAttachments(Message $message): void
    {
        if ($this->pendingAttachments === [] && $this->pendingAttachmentIds === []) {
            return;
        }

        $disk = (string) config('bonfire.disk', 'public');

        foreach ($this->pendingAttachments as $upload) {
            $path = $upload->store('bonfire/attachments', $disk);

            if ($path === false) {
                continue;
            }

            Attachment::query()->create([
                'message_id' => $message->id,
                'disk' => $disk,
                'path' => $path,
                'filename' => $upload->getClientOriginalName(),
                'mime_type' => $upload->getMimeType() ?? 'application/octet-stream',
                'size' => $upload->getSize() ?? 0,
                'created_at' => now(),
            ]);
        }

        // Clone selected recent attachments by referencing the same stored path.
        if ($this->pendingAttachmentIds !== []) {
            $member = $this->member();
            if ($member !== null) {
                $existing = Attachment::query()
                    ->whereIn('id', $this->pendingAttachmentIds)
                    ->whereHas('message', fn ($q) => $q->where('member_id', $member->id))
                    ->get();

                foreach ($existing as $src) {
                    Attachment::query()->create([
                        'message_id' => $message->id,
                        'disk' => $src->disk,
                        'path' => $src->path,
                        'filename' => $src->filename,
                        'mime_type' => $src->mime_type,
                        'size' => $src->size,
                        'created_at' => now(),
                    ]);
                }
            }
        }
    }
};
