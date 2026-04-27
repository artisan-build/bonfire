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

    public function mount(): void
    {
        $member = Bonfire::memberFor(auth()->user());

        abort_unless(
            $member !== null && $member->is_active && $member->hasRoleAtLeast(BonfireRole::Admin),
            403,
        );
    }

    /**
     * @return Collection<int, Room>
     */
    #[Computed]
    public function rooms(): Collection
    {
        return Room::query()
            ->where('tenant_id', Bonfire::tenantId())
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Room>
     */
    #[Computed]
    public function deletedRooms(): Collection
    {
        return Room::onlyTrashed()
            ->where('tenant_id', Bonfire::tenantId())
            ->orderByDesc('deleted_at')
            ->get();
    }

    public function restoreRoom(int $roomId): void
    {
        $room = Room::onlyTrashed()->where('tenant_id', Bonfire::tenantId())->find($roomId);

        if ($room !== null) {
            $room->restore();
            unset($this->rooms, $this->deletedRooms);
            $this->dispatch('bonfire:rooms-changed');
        }
    }

    public function forceDeleteRoom(int $roomId): void
    {
        $room = Room::onlyTrashed()->where('tenant_id', Bonfire::tenantId())->find($roomId);

        if ($room !== null) {
            $room->forceDelete();
            unset($this->rooms, $this->deletedRooms);
            $this->dispatch('bonfire:rooms-changed');
        }
    }

    public function deleteRoom(int $roomId): void
    {
        $room = Room::query()->where('tenant_id', Bonfire::tenantId())->find($roomId);

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
            ->where('tenant_id', Bonfire::tenantId())
            ->orderBy('display_name')
            ->get();
    }

    public function createRoom(): void
    {
        $name = trim($this->newName);

        if ($name === '') {
            return;
        }

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
            'tenant_id' => Bonfire::tenantId(),
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
        Bonfire::promote($target, BonfireRole::from($role));
        unset($this->members);
    }

    public function toggleActive(int $memberId): void
    {
        $target = Member::query()->findOrFail($memberId);

        if ($target->is_active) {
            Bonfire::deactivate($target);
        } else {
            Bonfire::reactivate($target);
        }

        unset($this->members);
    }
};
