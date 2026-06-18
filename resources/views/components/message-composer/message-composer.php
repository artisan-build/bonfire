<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Actions\CancelScheduledMessage;
use ArtisanBuild\Bonfire\Actions\ReleaseDueScheduledMessages;
use ArtisanBuild\Bonfire\Actions\SendMessage;
use ArtisanBuild\Bonfire\Actions\SendScheduledMessageNow;
use ArtisanBuild\Bonfire\Actions\UpdateScheduledMessage;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Attachment;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\FindOwnedScheduledMessage;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
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

    /**
     * Enforce the server-side size + MIME allowlist on freshly uploaded
     * attachments and bound the count of cloned/existing attachment references.
     * Rejects the whole send (validation exception) if any pending upload is
     * too large or of a disallowed type, so nothing is silently dropped. SVG
     * is excluded by the configured allowlist.
     */
    private function validatePendingAttachments(): void
    {
        if ($this->pendingAttachments === [] && $this->pendingAttachmentIds === []) {
            return;
        }

        $maxKb = (int) config('bonfire.max_attachment_size_kb', 10240);
        /** @var array<int, string> $allowedTypes */
        $allowedTypes = (array) config('bonfire.allowed_attachment_types', []);

        $rules = [];
        $attributes = [];

        if ($this->pendingAttachments !== []) {
            $rules['pendingAttachments'] = ['array', 'max:10'];
            $rules['pendingAttachments.*'] = ['file', 'max:'.$maxKb, 'mimetypes:'.implode(',', $allowedTypes)];
            $attributes['pendingAttachments.*'] = __('attachment');
        }

        if ($this->pendingAttachmentIds !== []) {
            $rules['pendingAttachmentIds'] = ['array', 'max:10'];
        }

        $this->validate($rules, attributes: $attributes);
    }

    /**
     * Cap the length of the free-text message body. The body may legitimately
     * be empty when the message carries only attachments or a poll, so it is
     * nullable; this only enforces an upper bound to keep unbounded text out of
     * the database.
     */
    private function validateBody(): void
    {
        $this->validate(['body' => ['nullable', 'string', 'max:10000']]);
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

        $this->validateBody();
        $this->validatePendingAttachments();

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

        $message = resolve(SendMessage::class)(
            $member,
            $this->room,
            $effectiveBody,
            $parent,
            $scheduledAt,
            $poll,
            $this->pendingAttachments,
            $this->pendingAttachmentIds,
            $this->alsoSendToChannel,
        );

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
        $member = $this->member();

        if ($member !== null) {
            $released = resolve(ReleaseDueScheduledMessages::class)($member);

            if ($released->isNotEmpty()) {
                $this->dispatch('bonfire-scheduled-released');
            }
        }

        $this->lastScheduledAt = null;
        $this->lastScheduledMessageId = null;
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
        $member = $this->member();
        if ($member === null) {
            return;
        }

        Validator::make(['body' => $body], ['body' => ['nullable', 'string', 'max:10000']])->validate();

        $message = resolve(UpdateScheduledMessage::class)($member, $id, $body, Carbon::parse($newTime));
        if ($message === null) {
            return;
        }

        if ($id === $this->lastScheduledMessageId) {
            $this->lastScheduledAt = $message->scheduled_for->toIso8601String();
        }

        unset($this->lastScheduledMessage, $this->scheduledMessages);
    }

    public function cancelScheduledMessage(int $id): void
    {
        $member = $this->member();
        if ($member === null) {
            return;
        }

        $message = resolve(CancelScheduledMessage::class)($member, $id);
        if ($message === null) {
            return;
        }

        if ($id === $this->lastScheduledMessageId) {
            $this->lastScheduledAt = null;
            $this->lastScheduledMessageId = null;
        }

        unset($this->lastScheduledMessage, $this->scheduledMessages);
    }

    public function sendScheduledNow(int $id): void
    {
        $member = $this->member();
        if ($member === null) {
            return;
        }

        $message = resolve(SendScheduledMessageNow::class)($member, $id);
        if ($message === null) {
            return;
        }

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

        return resolve(FindOwnedScheduledMessage::class)($member, $id);
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

        $this->validateBody();

        $this->authorize('post', $this->room);

        $member = Bonfire::memberFor(auth()->user());
        abort_unless($member !== null && $member->is_active, 403);

        $parent = $this->resolveParent();

        $bodyHtml = trim($this->body);
        $bodyPlain = trim(strip_tags(html_entity_decode($bodyHtml, ENT_QUOTES | ENT_HTML5)));
        $effectiveBody = $bodyPlain === '' ? $poll['question'] : $bodyHtml;

        resolve(SendMessage::class)(
            $member,
            $this->room,
            $effectiveBody,
            $parent,
            null,
            $poll,
            [],
            [],
            $this->alsoSendToChannel,
        );

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
};
