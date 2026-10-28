<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\RoomAccess;

/**
 * Apply a single-field update to a room from the admin panel.
 *
 * The `name`/`description` fields are trimmed (empty becomes null); the
 * `private`/`archived`/`announcements` fields toggle the matching RoomType bit;
 * any other field is a no-op. Returns true when a write occurred so the Livewire
 * component can mirror its original dispatch-on-change behavior. The admin
 * authorization, room lookup, and UI dispatch remain in the component.
 */
final class UpdateRoom
{
    public function __invoke(Room $room, string $field, string|bool $value, ?Member $member = null): bool
    {
        if ($member === null) {
            RoomAccess::authorizeGate($room);
        } else {
            RoomAccess::authorizeGate($room, $member);
        }

        if (in_array($field, ['name', 'description'], true)) {
            $room->setAttribute($field, is_string($value) ? (trim($value) ?: null) : null);
            $room->save();

            return true;
        }

        $flag = match ($field) {
            'private' => RoomType::Private,
            'archived' => RoomType::Archived,
            'announcements' => RoomType::Announcements,
            default => null,
        };

        if ($flag === null) {
            return false;
        }

        $room->type = $value
            ? RoomType::add($room->type, $flag)
            : RoomType::remove($room->type, $flag);
        $room->save();

        return true;
    }
}
