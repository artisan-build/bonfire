<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\RoomAccess;

/**
 * Permanently delete a room.
 *
 * The admin authorization, trashed-room lookup, null guard, and UI dispatch
 * remain in the Livewire component; this action owns only the hard-delete write.
 */
final class ForceDeleteRoom
{
    public function __invoke(Room $room, ?Member $member = null): void
    {
        if ($member === null) {
            RoomAccess::authorizeGate($room);
        } else {
            RoomAccess::authorizeGate($room, $member);
        }

        $room->forceDelete();
    }
}
