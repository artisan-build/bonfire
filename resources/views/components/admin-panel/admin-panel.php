<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    public string $tab = 'rooms';

    public string $newName = '';

    public string $newDescription = '';

    public bool $newPrivate = false;

    public bool $newArchived = false;

    public bool $newAnnouncements = false;

    public ?int $expandedRoomId = null;

    public string $memberSearch = '';

    public function mount(): void
    {
        $this->authorize('administer', Member::class);
    }

    #[Computed]
    public function showFeatures(): bool
    {
        if (! class_exists('App\\Livewire\\Admin\\FeatureFlags')) {
            return false;
        }

        $user = auth()->user();

        return $user !== null && (bool) ($user->is_admin ?? false);
    }

    public function toggleRoomMembers(int $roomId): void
    {
        $this->expandedRoomId = $this->expandedRoomId === $roomId ? null : $roomId;
        $this->memberSearch = '';
    }

    public function addMemberToRoom(int $roomId, int $memberId): void
    {
        $this->authorize('manage', Room::class);

        $room = Room::query()->find($roomId);
        $member = Member::query()->find($memberId);

        if ($room === null || $member === null) {
            return;
        }

        $actor = Bonfire::memberFor(auth()->user());
        $room->addMember($member, $actor);

        unset($this->rooms);
        $this->dispatch('bonfire:rooms-changed');
    }

    public function removeMemberFromRoom(int $roomId, int $memberId): void
    {
        $this->authorize('manage', Room::class);

        $room = Room::query()->find($roomId);
        $member = Member::query()->find($memberId);

        if ($room === null || $member === null) {
            return;
        }

        $room->removeMember($member);

        unset($this->rooms);
        $this->dispatch('bonfire:rooms-changed');
    }

    /**
     * @return Collection<int, Room>
     */
    #[Computed]
    public function rooms(): Collection
    {
        return Room::query()
            ->with(['members' => function ($query): void {
                $query->orderBy('display_name');
            }])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Member>
     */
    #[Computed]
    public function availableMembers(): Collection
    {
        if ($this->expandedRoomId === null) {
            return collect();
        }

        // @phpstan-ignore property.notFound (Livewire #[Computed] $rooms property; Larastan does not resolve computed props on anonymous components)
        $room = $this->rooms->firstWhere('id', $this->expandedRoomId);

        if ($room === null) {
            return collect();
        }

        $assignedIds = $room->members->pluck('id')->all();

        $query = Member::query()
            ->whereNotIn('id', $assignedIds)
            ->orderBy('display_name');

        $term = trim($this->memberSearch);
        if ($term !== '') {
            $query->where('display_name', 'like', '%'.$term.'%');
        }

        return $query->limit(50)->get();
    }

    /**
     * @return Collection<int, Room>
     */
    #[Computed]
    public function deletedRooms(): Collection
    {
        return Room::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->get();
    }

    public function restoreRoom(int $roomId): void
    {
        $this->authorize('manage', Room::class);

        $room = Room::onlyTrashed()->find($roomId);

        if ($room !== null) {
            $room->restore();
            unset($this->rooms, $this->deletedRooms);
            $this->dispatch('bonfire:rooms-changed');
        }
    }

    public function forceDeleteRoom(int $roomId): void
    {
        $this->authorize('manage', Room::class);

        $room = Room::onlyTrashed()->find($roomId);

        if ($room !== null) {
            $room->forceDelete();
            unset($this->rooms, $this->deletedRooms);
            $this->dispatch('bonfire:rooms-changed');
        }
    }

    public function deleteRoom(int $roomId): void
    {
        $this->authorize('manage', Room::class);

        $room = Room::query()->find($roomId);

        if ($room !== null) {
            $room->delete();
            unset($this->rooms, $this->deletedRooms);
            $this->dispatch('bonfire:rooms-changed');
        }
    }

    /**
     * @return Collection<int, Member>
     */
    #[Computed]
    public function members(): Collection
    {
        return Member::query()
            ->orderBy('display_name')
            ->get();
    }

    public function createRoom(): void
    {
        $name = trim($this->newName);

        if ($name === '') {
            return;
        }

        $this->authorize('create', Room::class);

        $member = Bonfire::memberFor(auth()->user());
        abort_unless($member !== null, 403);

        $type = 0;
        if ($this->newPrivate) {
            $type = RoomType::add($type, RoomType::Private);
        }
        if ($this->newArchived) {
            $type = RoomType::add($type, RoomType::Archived);
        }
        if ($this->newAnnouncements) {
            $type = RoomType::add($type, RoomType::Announcements);
        }

        Room::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => trim($this->newDescription) ?: null,
            'type' => $type,
            'created_by' => $member->getKey(),
        ]);

        $this->reset(['newName', 'newDescription', 'newPrivate', 'newArchived', 'newAnnouncements']);
        unset($this->rooms);
        $this->dispatch('bonfire:rooms-changed');
    }

    public function updateRoom(int $roomId, string $field, string|bool $value): void
    {
        $this->authorize('manage', Room::class);

        $room = Room::query()->findOrFail($roomId);

        if (in_array($field, ['name', 'description'], true)) {
            $room->setAttribute($field, is_string($value) ? (trim($value) ?: null) : null);
            $room->save();
            unset($this->rooms);
            $this->dispatch('bonfire:rooms-changed');

            return;
        }

        $flag = match ($field) {
            'private' => RoomType::Private,
            'archived' => RoomType::Archived,
            'announcements' => RoomType::Announcements,
            default => null,
        };

        if ($flag === null) {
            return;
        }

        $room->type = $value
            ? RoomType::add($room->type, $flag)
            : RoomType::remove($room->type, $flag);
        $room->save();
        unset($this->rooms);
        $this->dispatch('bonfire:rooms-changed');
    }

    public function changeRole(int $memberId, string $role): void
    {
        $target = Member::query()->findOrFail($memberId);
        $this->authorize('changeRole', $target);
        Bonfire::promote($target, BonfireRole::from($role));
        unset($this->members);
    }

    public function toggleActive(int $memberId): void
    {
        $target = Member::query()->findOrFail($memberId);
        $this->authorize('toggleActive', $target);

        if ($target->is_active) {
            Bonfire::deactivate($target);
        } else {
            Bonfire::reactivate($target);
        }

        unset($this->members);
    }

    public function kickMember(int $memberId): void
    {
        $target = Member::query()
            ->find($memberId);

        if ($target === null) {
            return;
        }

        $this->authorize('kick', $target);

        $target->rooms()->detach();
        $target->delete();

        unset($this->members, $this->rooms);
        $this->dispatch('bonfire:rooms-changed');
    }
};
