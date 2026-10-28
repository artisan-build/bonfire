<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\RoomAccess;

/**
 * Detach a member from a room.
 *
 * The admin authorization, room/member lookups, and UI dispatch remain in the
 * Livewire component; this action owns only the detach write.
 */
final class RemoveMemberFromRoom
{
    public function __invoke(Room $room, Member $member, ?Member $removedBy = null): void
    {
        if ($removedBy === null) {
            RoomAccess::authorizeGate($room);
        } else {
            RoomAccess::authorizeGate($room, $removedBy);
        }

        $room->removeMember($member);
    }
}
