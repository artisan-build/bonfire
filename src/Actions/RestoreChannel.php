<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\RoomAccess;

/**
 * Restore a soft-deleted channel room.
 *
 * A room that is not trashed is a no-op. The admin authorization and trashed-room
 * lookup remain in the Livewire component; this action owns only the restore write.
 */
final class RestoreChannel
{
    public function __invoke(Room $room, ?Member $member = null): void
    {
        if ($member === null) {
            RoomAccess::authorizeGate($room);
        } else {
            RoomAccess::authorizeGate($room, $member);
        }

        if (! $room->trashed()) {
            return;
        }

        $room->restore();
    }
}
