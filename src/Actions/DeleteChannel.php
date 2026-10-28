<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\RoomAccess;

/**
 * Soft-delete a channel room.
 *
 * The admin authorization, fallback-room lookup, and redirect remain in the
 * Livewire component; this action owns only the soft-delete write.
 */
final class DeleteChannel
{
    public function __invoke(Room $room, ?Member $member = null): void
    {
        if ($member === null) {
            RoomAccess::authorizeGate($room);
        } else {
            RoomAccess::authorizeGate($room, $member);
        }

        $room->delete();
    }
}
