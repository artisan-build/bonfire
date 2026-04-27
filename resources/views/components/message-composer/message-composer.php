<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Attachment;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

return new class extends Component
{
    use WithFileUploads;

    public Room $room;

    public ?int $parentId = null;

    public string $body = '';

    public ?string $scheduledFor = null;

    public ?string $lastScheduledAt = null;

    public bool $alsoSendToChannel = false;

    /** @var array<int, UploadedFile> */
    public array $pendingAttachments = [];

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
        $tenantId = Bonfire::tenantId();

        return Member::query()
            ->where('tenant_id', $tenantId)
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

        if ($plain === '' && $this->pendingAttachments === [] && $this->pendingPoll === null) {
            return;
        }

        $member = Bonfire::memberFor(auth()->user());
        abort_unless($member !== null && $member->is_active, 403);
        $this->ensureMemberMayPost($member);

        $parent = $this->parentId !== null ? Message::query()->findOrFail($this->parentId) : null;

        // Staged poll wins over a typed /poll command.
        $poll = $this->pendingPoll ?? $this->parsePollCommand($plain);

        $effectiveBody = $plain === ''
            ? ($poll !== null ? (string) ($poll['question'] ?? '📎') : '📎')
            : $body;

        $scheduledAt = $this->scheduledFor !== null && $this->scheduledFor !== ''
            ? Carbon::parse($this->scheduledFor)
            : null;

        if ($scheduledAt !== null && $scheduledAt->isPast()) {
            $scheduledAt = null;
        }

        // /poll slash-command: replace body with the bare question (the slash-command
        // string itself is not useful as visible body). Staged poll: keep the user's
        // typed body so @mentions and accompanying text survive.
        if ($poll !== null && $this->pendingPoll === null) {
            $effectiveBody = $poll['question'];
        }

        $message = $this->createMessage($member, $parent, $effectiveBody, $scheduledAt, $poll);

        $this->storeAttachments($message);

        if ($parent !== null && $this->alsoSendToChannel && $scheduledAt === null) {
            Bonfire::postAs($member, $this->room, $effectiveBody, null);
        }

        if ($scheduledAt !== null) {
            $this->lastScheduledAt = $scheduledAt->toIso8601String();
        }

        $this->pendingPoll = null;
        $this->reset('body', 'pendingAttachments', 'scheduledFor', 'alsoSendToChannel');
        $this->dispatch('bonfire-own-message-sent');
    }

    /**
     * Parse `/poll Question | Option 1 | Option 2` from a plain-text body.
     *
     * @return array{question: string, options: array<int, string>}|null
     */
    private function parsePollCommand(string $plain): ?array
    {
        if (! preg_match('#^/poll\s+(.+)$#si', $plain, $matches)) {
            return null;
        }

        $parts = array_values(array_filter(
            array_map('trim', explode('|', $matches[1])),
            fn (string $part): bool => $part !== '',
        ));

        if (count($parts) < 3) {
            return null;
        }

        $question = array_shift($parts);
        $options = array_slice($parts, 0, 10);

        return [
            'question' => $question,
            'options' => array_values($options),
        ];
    }

    public function dismissScheduled(): void
    {
        $this->lastScheduledAt = null;
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
            'tenant_id' => Bonfire::tenantId(),
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
     * Reject if the room is announcement-only and the member is not at least a moderator.
     */
    private function ensureMemberMayPost(Member $member): void
    {
        if (! $this->room->isAnnouncements()) {
            return;
        }

        abort_unless($member->hasRoleAtLeast(BonfireRole::Moderator), 403);
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

        $member = Bonfire::memberFor(auth()->user());
        abort_unless($member !== null && $member->is_active, 403);
        $this->ensureMemberMayPost($member);

        $parent = $this->parentId !== null ? Message::query()->findOrFail($this->parentId) : null;

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
        if ($this->pendingAttachments === []) {
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
    }
};
