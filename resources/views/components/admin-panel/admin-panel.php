<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Actions\AddMemberToRoom;
use ArtisanBuild\Bonfire\Actions\CreateRoom;
use ArtisanBuild\Bonfire\Actions\DeleteChannel;
use ArtisanBuild\Bonfire\Actions\ForceDeleteRoom;
use ArtisanBuild\Bonfire\Actions\KickMember;
use ArtisanBuild\Bonfire\Actions\RemoveMemberFromRoom;
use ArtisanBuild\Bonfire\Actions\RestoreChannel;
use ArtisanBuild\Bonfire\Actions\ToggleMemberActive;
use ArtisanBuild\Bonfire\Actions\UpdateRoom;
use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
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
        resolve(AddMemberToRoom::class)($room, $member, $actor);

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

        resolve(RemoveMemberFromRoom::class)($room, $member);

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
            resolve(RestoreChannel::class)($room);
            unset($this->rooms, $this->deletedRooms);
            $this->dispatch('bonfire:rooms-changed');
        }
    }

    public function forceDeleteRoom(int $roomId): void
    {
        $this->authorize('manage', Room::class);

        $room = Room::onlyTrashed()->find($roomId);

        if ($room !== null) {
            resolve(ForceDeleteRoom::class)($room);
            unset($this->rooms, $this->deletedRooms);
            $this->dispatch('bonfire:rooms-changed');
        }
    }

    public function deleteRoom(int $roomId): void
    {
        $this->authorize('manage', Room::class);

        $room = Room::query()->find($roomId);

        if ($room !== null) {
            resolve(DeleteChannel::class)($room);
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
        $this->validate([
            'newName' => ['required', 'string', 'max:100'],
            'newDescription' => ['nullable', 'string', 'max:2000'],
        ]);

        $name = trim($this->newName);

        $this->authorize('create', Room::class);

        $member = Bonfire::memberFor(auth()->user());
        abort_unless($member !== null, 403);

        resolve(CreateRoom::class)(
            $member,
            $name,
            $this->newDescription,
            $this->newPrivate,
            $this->newArchived,
            $this->newAnnouncements,
        );

        $this->reset(['newName', 'newDescription', 'newPrivate', 'newArchived', 'newAnnouncements']);
        unset($this->rooms);
        $this->dispatch('bonfire:rooms-changed');
    }

    public function updateRoom(int $roomId, string $field, string|bool $value): void
    {
        $this->authorize('manage', Room::class);

        if (is_string($value) && in_array($field, ['name', 'description'], true)) {
            Validator::make(
                [$field => $value],
                [$field => $field === 'name'
                    ? ['nullable', 'string', 'max:100']
                    : ['nullable', 'string', 'max:2000']],
            )->validate();
        }

        $room = Room::query()->findOrFail($roomId);

        if (resolve(UpdateRoom::class)($room, $field, $value)) {
            unset($this->rooms);
            $this->dispatch('bonfire:rooms-changed');
        }
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

        resolve(ToggleMemberActive::class)($target);

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

        resolve(KickMember::class)($target);

        unset($this->members, $this->rooms);
        $this->dispatch('bonfire:rooms-changed');
    }
};
