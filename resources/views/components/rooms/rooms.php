<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Actions\AssignRoomToSection;
use ArtisanBuild\Bonfire\Actions\CreateChannel;
use ArtisanBuild\Bonfire\Actions\CreateChannelSection;
use ArtisanBuild\Bonfire\Actions\DeleteChannel;
use ArtisanBuild\Bonfire\Actions\DeleteChannelSection;
use ArtisanBuild\Bonfire\Actions\OpenDirectMessage;
use ArtisanBuild\Bonfire\Actions\RenameChannelSection;
use ArtisanBuild\Bonfire\Actions\RestoreChannel;
use ArtisanBuild\Bonfire\Actions\ToggleRoomStar;
use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\CallSession;
use ArtisanBuild\Bonfire\Models\ChannelSection;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\UnreadTracker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

return new class extends Component
{
    public string $filter = 'all';

    public string $sort = 'alpha';

    public ?int $currentRoomId = null;

    public string $newChannelName = '';

    public string $newChannelDescription = '';

    public bool $newChannelPrivate = false;

    public bool $newChannelAnnouncements = false;

    /** @var array<int, int> */
    public array $newChannelMemberIds = [];

    #[On('bonfire:star-toggled')]
    public function onStarToggled(): void
    {
        unset($this->starredRoomIds, $this->visibleRooms);
    }

    #[On('bonfire:rooms-changed')]
    public function onRoomsChanged(): void
    {
        unset($this->visibleRooms);
    }

    #[On('bonfire:read-state-changed')]
    public function onReadStateChanged(?int $roomId = null): void
    {
        if ($roomId !== null) {
            $this->currentRoomId = $roomId;
        }
        unset($this->visibleRooms);
    }

    #[On('bonfire:member-updated')]
    public function onMemberUpdated(): void
    {
        unset($this->visibleRooms, $this->directMessageMembers, $this->currentMember);
    }

    #[On('bonfire-filter')]
    public function setFilter(string $value): void
    {
        $this->filter = in_array($value, ['all', 'unread'], true) ? $value : 'all';
        unset($this->visibleRooms);
    }

    #[On('bonfire-sort')]
    public function setSort(string $value): void
    {
        $this->sort = in_array($value, ['alpha', 'recent'], true) ? $value : 'alpha';
        unset($this->visibleRooms);
    }

    #[Computed]
    public function currentMember(): ?Member
    {
        return Bonfire::memberFor(auth()->user());
    }

    /**
     * IDs of rooms the current member has starred.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function starredRoomIds(): array
    {
        $member = $this->currentMember();

        if ($member === null) {
            return [];
        }

        return DB::table('bonfire_starred_rooms')
            ->where('member_id', $member->id)
            ->pluck('room_id')
            ->all();
    }

    /**
     * Rooms visible to the current member.
     *
     * @return Collection<int, Room>
     */
    #[Computed]
    public function visibleRooms(): Collection
    {
        $member = $this->currentMember();

        $query = Room::query()
            ->withMax('messages as last_message_at', 'created_at')
            ->orderByRaw('(type & ?) > 0', [RoomType::Archived->value]);

        if ($this->sort === 'recent') {
            $query->orderByRaw('COALESCE(last_message_at, created_at) DESC');
        } else {
            $query->orderBy('name');
        }

        if ($member === null || ! $member->is_active) {
            $rooms = $query->whereRaw('(type & ?) = 0', [RoomType::Private->value])->get();
        } else {
            $privateRoomIds = $member->rooms()->pluck('bonfire_rooms.id');

            $rooms = $query->where(function ($q) use ($privateRoomIds): void {
                $q->whereRaw('(type & ?) = 0', [RoomType::Private->value])
                    ->orWhereIn('id', $privateRoomIds);
            })->get();
        }

        $tracker = resolve(UnreadTracker::class);
        $starredIds = $this->starredRoomIds();

        $activeRoomId = $this->currentRoomId;
        $rooms->each(function (Room $room) use ($tracker, $member, $starredIds, $activeRoomId): void {
            // The room you're currently viewing never shows an unread indicator,
            // regardless of what the underlying lastReadAt says. Otherwise the
            // dot lingers when the rooms component renders before room-show.mount
            // has had a chance to call markRead.
            $hasUnread = $room->id === $activeRoomId
                ? false
                : $tracker->hasUnread($room, $member);
            $room->setAttribute('has_unread', $hasUnread);
            $room->setAttribute('is_starred', in_array($room->id, $starredIds, true));
        });

        if ($this->filter === 'unread') {
            return $rooms->filter(fn (Room $room) => (bool) $room->has_unread)->values();
        }

        return $rooms;
    }

    /**
     * @return Collection<int, ChannelSection>
     */
    #[Computed]
    public function channelSections(): Collection
    {
        $member = $this->currentMember();

        if ($member === null) {
            return collect();
        }

        return ChannelSection::query()
            ->where('member_id', $member->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, int> Map of roomId => sectionId (if assigned)
     */
    #[Computed]
    public function roomSectionMap(): array
    {
        $member = $this->currentMember();

        if ($member === null) {
            return [];
        }

        return DB::table('bonfire_member_room')
            ->where('member_id', $member->id)
            ->whereNotNull('section_id')
            ->pluck('section_id', 'room_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public function createSection(string $name): void
    {
        $member = $this->currentMember();

        if ($member === null) {
            return;
        }

        resolve(CreateChannelSection::class)($member, $name);

        unset($this->channelSections);
    }

    public function renameSection(int $sectionId, string $name): void
    {
        $member = $this->currentMember();
        if ($member === null) {
            return;
        }

        resolve(RenameChannelSection::class)($member, $sectionId, $name);

        unset($this->channelSections);
    }

    public function deleteSection(int $sectionId): void
    {
        $member = $this->currentMember();
        if ($member === null) {
            return;
        }

        resolve(DeleteChannelSection::class)($member, $sectionId);

        unset($this->channelSections, $this->roomSectionMap);
    }

    public function assignRoomToSection(int $roomId, ?int $sectionId): void
    {
        $member = $this->currentMember();
        if ($member === null) {
            return;
        }

        resolve(AssignRoomToSection::class)($member, $roomId, $sectionId);

        unset($this->roomSectionMap);
    }

    /**
     * @return Collection<int, Member>
     */
    #[Computed]
    public function directMessageMembers(): Collection
    {
        $current = $this->currentMember();

        return Member::query()
            ->where('is_active', true)
            ->when($current !== null, fn ($q) => $q->where('id', '!=', $current->id))
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'avatar_url', 'is_away', 'status_emoji', 'status_text']);
    }

    /**
     * Open or create a direct message room with the given member.
     *
     * @param  array<string, mixed>  $payload
     */
    #[On('bonfire-open-dm')]
    public function openDmFromEvent(array $payload): mixed
    {
        $memberId = (int) ($payload['memberId'] ?? 0);
        if ($memberId <= 0) {
            return null;
        }

        return $this->openDm($memberId);
    }

    public function openDm(int $memberId): mixed
    {
        $current = $this->currentMember();
        $target = Member::query()->find($memberId);

        if ($current === null || $target === null || $current->id === $target->id) {
            return null;
        }

        $room = resolve(OpenDirectMessage::class)($current, $target);

        return $this->redirect(route('bonfire.room.show', $room), navigate: true);
    }

    /**
     * @return array<int, int>
     */
    #[Computed]
    public function activeMeetingRoomIds(): array
    {
        return Message::query()
            ->where('body', 'like', '%data-bonfire-join-meeting%')
            ->where('created_at', '>=', now()->subHours(2))
            ->pluck('room_id')
            ->unique()
            ->values()
            ->all();
    }

    #[On('bonfire:meeting-changed')]
    public function onMeetingChanged(): void
    {
        unset($this->activeMeetingRoomIds);
    }

    #[On('bonfire:call-state-changed')]
    public function onCallStateChanged(): void
    {
        unset($this->memberIdsInCall, $this->directMessageMembers);
    }

    /**
     * @return array<int, int>
     */
    #[Computed]
    public function memberIdsInCall(): array
    {
        return CallSession::query()
            ->whereIn('status', ['ringing', 'active'])
            ->get(['caller_member_id', 'callee_member_id'])
            ->flatMap(fn (CallSession $s) => [$s->caller_member_id, $s->callee_member_id])
            ->unique()
            ->values()
            ->all();
    }

    #[Computed]
    public function isAdmin(): bool
    {
        $member = $this->currentMember();

        return $member !== null && $member->is_active && $member->hasRoleAtLeast(BonfireRole::Admin);
    }

    /**
     * Create a new channel room and redirect to it.
     */
    public function createChannel(): mixed
    {
        abort_unless($this->isAdmin(), 403);

        $name = trim($this->newChannelName);
        if ($name === '') {
            return null;
        }

        $member = $this->currentMember();
        if ($member === null) {
            return null;
        }

        $room = resolve(CreateChannel::class)(
            $member,
            $name,
            $this->newChannelDescription,
            $this->newChannelPrivate,
            $this->newChannelAnnouncements,
            $this->newChannelMemberIds,
        );

        $this->reset(['newChannelName', 'newChannelDescription', 'newChannelPrivate', 'newChannelAnnouncements', 'newChannelMemberIds']);
        unset($this->visibleRooms);

        $this->dispatch('modal-close', name: 'create-channel');

        return $this->redirect(route('bonfire.room.show', $room), navigate: true);
    }

    /**
     * Soft-delete a channel, redirecting if the user was viewing it.
     */
    public function deleteChannel(int $roomId, ?int $currentRoomId = null): mixed
    {
        abort_unless($this->isAdmin(), 403);

        $room = Room::query()->find($roomId);
        if ($room === null) {
            return null;
        }

        resolve(DeleteChannel::class)($room);
        unset($this->visibleRooms);

        if ($currentRoomId === $roomId) {
            $fallback = Room::query()
                ->whereRaw('(type & ?) = 0', [RoomType::Archived->value])
                ->where('slug', 'not like', 'dm-%')
                ->orderBy('name')
                ->first();

            $target = $fallback !== null
                ? route('bonfire.room.show', $fallback)
                : route('bonfire.index');

            return $this->redirect($target, navigate: true);
        }

        return null;
    }

    public function restoreChannel(int $roomId): void
    {
        abort_unless($this->isAdmin(), 403);

        $room = Room::withTrashed()->find($roomId);
        if ($room === null) {
            return;
        }

        resolve(RestoreChannel::class)($room);
        unset($this->visibleRooms);
    }

    public function toggleStar(int $roomId): void
    {
        $member = $this->currentMember();

        if ($member === null) {
            return;
        }

        $room = Room::withTrashed()->find($roomId);
        if ($room === null) {
            return;
        }

        resolve(ToggleRoomStar::class)($member, $room);

        unset($this->starredRoomIds, $this->visibleRooms);

        $this->dispatch('bonfire:star-toggled');
    }
};
