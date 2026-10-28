<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\RoomAccess;

/**
 * Add a member to a room, recording the acting member as the adder.
 *
 * The admin authorization, room/member lookups, actor resolution, and UI
 * dispatch remain in the Livewire component; this action owns only the write.
 */
final class AddMemberToRoom
{
    public function __invoke(Room $room, Member $member, ?Member $addedBy = null): void
    {
        if ($addedBy === null) {
            RoomAccess::authorizeGate($room);
        } else {
            RoomAccess::authorizeGate($room, $addedBy);
        }

        $room->addMember($member, $addedBy);
    }
}
