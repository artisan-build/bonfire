<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Actions\DeleteMessage;
use ArtisanBuild\Bonfire\Actions\EditMessage;
use ArtisanBuild\Bonfire\Actions\ForwardMessage;
use ArtisanBuild\Bonfire\Actions\MarkRoomRead;
use ArtisanBuild\Bonfire\Actions\ReleaseDueScheduledMessages;
use ArtisanBuild\Bonfire\Actions\TogglePin;
use ArtisanBuild\Bonfire\Actions\TogglePollVote;
use ArtisanBuild\Bonfire\Actions\ToggleReaction;
use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

return new class extends Component
{
    use WithPagination;

    public Room $room;

    public int $perPage = 40;

    public string $search = '';

    public ?string $previousReadAt = null;

    public function mount(Room $room, ?string $previousReadAt = null): void
    {
        $this->room = $room;
        $this->previousReadAt = $previousReadAt;
    }

    #[On('bonfire-search')]
    public function setSearch(string $value): void
    {
        $this->search = trim($value);
        $this->resetPage();
        unset($this->messages);
    }

    /**
     * Paginated list of root messages in the room.
     *
     * @return CursorPaginator<int, Message>
     */
    #[Computed]
    public function messages(): CursorPaginator
    {
        $query = Message::query()
            ->with(['member', 'replies', 'attachments', 'linkPreview', 'pollVotes.member'])
            ->where('room_id', $this->room->getKey())
            ->whereNull('parent_id')
            ->where(fn ($q) => $q->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now()));

        if ($this->search !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], $this->search).'%';
            $query->where('body', 'like', $needle);
        }

        return $query->oldest()->cursorPaginate($this->perPage);
    }

    #[On('echo-presence:bonfire.room.{room.id},.message.posted')]
    public function onMessagePosted(): void
    {
        resolve(ReleaseDueScheduledMessages::class)(room: $this->room);

        // Keep the user's lastReadAt fresh while they're viewing — otherwise
        // messages that arrive during their visit leave the channel marked
        // unread when they navigate away.
        $member = Bonfire::memberFor(auth()->user());
        if ($member !== null) {
            resolve(MarkRoomRead::class)($this->room, $member);
            $this->dispatch('bonfire:read-state-changed', roomId: $this->room->id);
        }

        unset($this->messages);
    }

    #[On('bonfire-scheduled-released')]
    public function onScheduledReleased(): void
    {
        unset($this->messages);
    }

    #[On('echo-presence:bonfire.room.{room.id},.message.deleted')]
    public function onMessageDeleted(): void
    {
        unset($this->messages);
    }

    #[On('bonfire:member-updated')]
    public function onMemberUpdated(): void
    {
        unset($this->messages);
    }

    public function openThread(int $messageId): void
    {
        $this->dispatch('thread-open', messageId: $messageId);
    }

    #[Computed]
    public function currentMember(): ?Member
    {
        return Bonfire::memberFor(auth()->user());
    }

    /**
     * Guard against acting on a message looked up by raw id that does not belong
     * to the room this component is bound to.
     */
    private function messageInRoom(Message $message): bool
    {
        return (int) $message->room_id === (int) $this->room->getKey();
    }

    public function canDelete(Message $message): bool
    {
        $member = $this->currentMember();

        if ($member === null || ! $member->is_active) {
            return false;
        }

        if ($message->member_id === $member->getKey()) {
            return true;
        }

        return $member->hasRoleAtLeast(BonfireRole::Moderator);
    }

    public function deleteMessage(int $messageId): void
    {
        $message = Message::query()->findOrFail($messageId);

        abort_unless($this->messageInRoom($message), 403);
        $this->authorize('delete', $message);

        resolve(DeleteMessage::class)($message);

        unset($this->messages);
    }

    public function canPin(Message $message): bool
    {
        $member = $this->currentMember();
        if ($member === null || ! $member->is_active) {
            return false;
        }
        if ($message->trashed()) {
            return false;
        }
        if ($this->room->isPrivate() && ! $this->room->hasMember($member)) {
            return false;
        }

        return true;
    }

    /**
     * Add or remove the current member's reaction with a specific emoji on a message.
     * Multiple emoji can coexist per (message, member); the unique key is
     * (message_id, member_id, emoji), so re-clicking the same emoji removes it.
     */
    public function toggleReaction(int $messageId, string $emoji): void
    {
        $member = $this->currentMember();
        if ($member === null || ! $member->is_active) {
            return;
        }

        // Limit emoji length so a malformed payload can't overflow the column.
        $emoji = trim($emoji);
        if ($emoji === '' || mb_strlen($emoji) > 16) {
            return;
        }

        $message = Message::query()
            ->whereKey($messageId)
            ->first();

        if ($message === null || ! $this->messageInRoom($message)) {
            return;
        }

        $this->authorize('react', $message);

        resolve(ToggleReaction::class)($member, $message, $emoji);

        unset($this->messages, $this->reactionsByMessage);
    }

    /**
     * Aggregate reactions for the messages currently in view.
     *
     * @return array<int, array<string, array{count: int, names: array<int, string>, mine: bool}>>
     */
    #[Computed]
    public function reactionsByMessage(): array
    {
        // @phpstan-ignore property.notFound (Livewire #[Computed] $messages property; Larastan does not resolve computed props on anonymous components)
        $messageIds = $this->messages->pluck('id')->all();
        if ($messageIds === []) {
            return [];
        }

        $myMemberId = $this->currentMember()?->id;

        $rows = DB::table('bonfire_reactions')
            ->join('bonfire_members', 'bonfire_reactions.member_id', '=', 'bonfire_members.id')
            ->whereIn('bonfire_reactions.message_id', $messageIds)
            ->orderBy('bonfire_reactions.created_at')
            ->get(['bonfire_reactions.message_id', 'bonfire_reactions.member_id', 'bonfire_reactions.emoji', 'bonfire_members.display_name']);

        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r->message_id][$r->emoji] ??= ['count' => 0, 'names' => [], 'mine' => false];
            $grouped[$r->message_id][$r->emoji]['count']++;
            $grouped[$r->message_id][$r->emoji]['names'][] = $r->display_name;
            if ($myMemberId !== null && (int) $r->member_id === $myMemberId) {
                $grouped[$r->message_id][$r->emoji]['mine'] = true;
            }
        }

        return $grouped;
    }

    public function togglePin(int $messageId): void
    {
        $message = Message::query()->findOrFail($messageId);
        abort_unless($this->messageInRoom($message), 403);
        $this->authorize('pin', $message);

        $member = $this->currentMember();
        abort_unless($member !== null, 403);

        resolve(TogglePin::class)($member, $message);

        unset($this->messages);
        $this->dispatch('bonfire:pins-changed', roomId: $this->room->id);
    }

    public function canEdit(Message $message): bool
    {
        $member = $this->currentMember();

        if ($member === null || ! $member->is_active) {
            return false;
        }

        if ($message->trashed()) {
            return false;
        }

        // Only the author can edit. Admins/mods can still delete, but editing
        // someone else's words would be weird.
        return $message->member_id === $member->getKey();
    }

    public function editMessage(int $messageId, string $body): void
    {
        $message = Message::query()->findOrFail($messageId);

        abort_unless($this->messageInRoom($message), 403);
        $this->authorize('update', $message);

        $clean = trim($body);
        if ($clean === '' || $clean === '<p></p>') {
            return; // no-op on empty edit; user can delete instead
        }

        Validator::make(['body' => $clean], ['body' => ['required', 'string', 'max:10000']])->validate();

        resolve(EditMessage::class)($message, $clean);

        unset($this->messages);
    }

    #[On('echo-presence:bonfire.room.{room.id},.message.edited')]
    public function onMessageEdited(): void
    {
        unset($this->messages);
    }

    /**
     * @return Collection<int, Room>
     */
    #[Computed]
    public function forwardableRooms(): Collection
    {
        $member = $this->currentMember();

        if ($member === null) {
            return collect();
        }

        $memberRoomIds = DB::table('bonfire_member_room')
            ->where('member_id', $member->id)
            ->pluck('room_id');

        return Room::query()
            ->whereNull('deleted_at')
            ->whereRaw('(type & ?) = 0', [RoomType::Archived->value])
            ->where(function ($q) use ($memberRoomIds): void {
                $q->whereRaw('(type & ?) = 0', [RoomType::Private->value])
                    ->orWhereIn('id', $memberRoomIds);
            })
            ->where('slug', 'not like', 'dm-%')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'type'])
            ->values();
    }

    public function forwardMessage(int $messageId, int $targetRoomId, string $note = ''): void
    {
        $source = Message::query()->with('member')->findOrFail($messageId);
        $target = Room::query()->findOrFail($targetRoomId);
        $member = $this->currentMember();

        abort_unless($member !== null && $member->is_active, 403);

        // The forwarded message must be one this component's room actually shows;
        // a member must not forward a message they hold only by raw id.
        abort_unless($this->messageInRoom($source), 403);

        // And they must be able to read the source room in the first place.
        $this->authorize('view', $this->room);

        // The destination must be writable: not archived, and (if private) joined.
        $this->authorize('forwardInto', $target);

        resolve(ForwardMessage::class)($member, $source, $target, $note);

        $this->dispatch('bonfire:rooms-changed');
        $this->dispatch('bonfire-forwarded', targetRoomId: $target->id, targetRoomSlug: $target->slug);
    }

    public function togglePollVote(int $messageId, int $optionIndex): void
    {
        $message = Message::query()->findOrFail($messageId);

        if (! $message->isPoll()) {
            return;
        }

        if (! $this->messageInRoom($message)) {
            abort(403);
        }

        $this->authorize('vote', $message);

        $member = $this->currentMember();
        abort_unless($member !== null && $member->is_active, 403);

        $optionCount = count($message->poll['options'] ?? []);
        if ($optionIndex < 0 || $optionIndex >= $optionCount) {
            return;
        }

        resolve(TogglePollVote::class)($member, $message, $optionIndex);

        unset($this->messages);
    }

    #[On('echo-presence:bonfire.room.{room.id},.poll.voted')]
    public function onPollVoted(): void
    {
        unset($this->messages);
    }
};
