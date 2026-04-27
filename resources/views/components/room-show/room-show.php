<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Attachment;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\UnreadTracker;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

return new class extends Component
{
    public Room $room;

    public ?int $openThreadId = null;

    public function mount(Room $room): void
    {
        $this->room = $room;

        abort_unless($this->canView(), 403);

        resolve(UnreadTracker::class)->markRead($room, $this->currentMember());
    }

    #[Computed]
    public function currentMember(): ?Member
    {
        return Bonfire::memberFor(auth()->user());
    }

    #[Computed]
    public function canPost(): bool
    {
        $member = $this->currentMember();

        if ($member === null || ! $member->is_active) {
            return false;
        }

        if ($this->room->isArchived()) {
            return false;
        }

        if ($this->room->isAnnouncements()) {
            return $member->hasRoleAtLeast(BonfireRole::Moderator);
        }

        return $this->room->isAccessibleBy($member);
    }

    #[On('thread-open')]
    public function openThread(int $messageId): void
    {
        $this->openThreadId = $messageId;
    }

    #[On('thread-close')]
    public function closeThread(): void
    {
        $this->openThreadId = null;
    }

    /**
     * Members of the current room.
     *
     * @return Collection<int, Member>
     */
    #[Computed]
    public function channelMembers(): Collection
    {
        return $this->room->members()
            ->orderBy('display_name')
            ->get([
                'bonfire_members.id',
                'display_name',
                'avatar_url',
                'memberable_type',
                'memberable_id',
                'phone',
                'timezone',
                'is_away',
                'status_emoji',
                'status_text',
            ]);
    }

    #[On('bonfire:member-updated')]
    public function onMemberUpdated(): void
    {
        unset($this->channelMembers);
        $this->room->unsetRelations();
        $this->room->refresh();
    }

    /**
     * Pinned messages in the current room.
     *
     * @return Collection<int, Message>
     */
    #[Computed]
    public function pinnedMessages(): Collection
    {
        return Message::query()
            ->with('member')
            ->where('room_id', $this->room->id)
            ->whereNotNull('pinned_at')
            ->orderByDesc('pinned_at')
            ->limit(50)
            ->get();
    }

    #[On('bonfire:pins-changed')]
    public function onPinsChanged(): void
    {
        unset($this->pinnedMessages);
    }

    /**
     * File attachments in the current room.
     *
     * @return Collection<int, Attachment>
     */
    #[Computed]
    public function roomAttachments(): Collection
    {
        return Attachment::query()
            ->with(['message.member'])
            ->whereHas('message', fn ($q) => $q->where('room_id', $this->room->id))
            ->orderByDesc('id')
            ->limit(200)
            ->get();
    }

    #[On('bonfire:attachments-changed')]
    public function onAttachmentsChanged(): void
    {
        unset($this->roomAttachments);
    }

    #[On('echo-presence:bonfire.room.{room.id},.message.posted')]
    public function onRoomMessagePosted(): void
    {
        unset($this->roomAttachments);
    }

    #[On('echo-presence:bonfire.room.{room.id},.message.deleted')]
    public function onRoomMessageDeleted(): void
    {
        unset($this->roomAttachments);
        unset($this->pinnedMessages);
    }

    #[Computed]
    public function isStarred(): bool
    {
        $member = $this->currentMember();

        if ($member === null) {
            return false;
        }

        return DB::table('bonfire_starred_rooms')
            ->where('member_id', $member->id)
            ->where('room_id', $this->room->id)
            ->exists();
    }

    public function toggleStar(): void
    {
        $member = $this->currentMember();

        if ($member === null) {
            return;
        }

        $exists = DB::table('bonfire_starred_rooms')
            ->where('member_id', $member->id)
            ->where('room_id', $this->room->id)
            ->exists();

        if ($exists) {
            DB::table('bonfire_starred_rooms')
                ->where('member_id', $member->id)
                ->where('room_id', $this->room->id)
                ->delete();
        } else {
            DB::table('bonfire_starred_rooms')->insert([
                'member_id' => $member->id,
                'room_id' => $this->room->id,
                'created_at' => now(),
            ]);
        }

        unset($this->isStarred);

        $this->dispatch('bonfire:star-toggled');
    }

    /**
     * Leave the current private channel and redirect to the index.
     */
    public function leaveChannel(): mixed
    {
        $member = $this->currentMember();

        if ($member === null || $this->room->isPrivate() === false) {
            return null;
        }

        $this->room->removeMember($member);

        return $this->redirect(route('bonfire.index'), navigate: true);
    }

    protected function canView(): bool
    {
        $member = Bonfire::memberFor(auth()->user());

        if (! $this->room->isPrivate()) {
            return true;
        }

        if ($member === null) {
            return false;
        }

        return $this->room->isAccessibleBy($member);
    }
};
