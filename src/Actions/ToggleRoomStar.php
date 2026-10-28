<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\RoomAccess;
use Illuminate\Support\Facades\DB;

/**
 * Star or unstar a room for a member. Returns the resulting state: true when the
 * room is now starred, false when it was unstarred.
 *
 * The Livewire component keeps its own state refresh and UI dispatch.
 */
final class ToggleRoomStar
{
    public function __invoke(Member $member, Room $room): bool
    {
        RoomAccess::authorizeGate($room, $member);

        $exists = DB::table('bonfire_starred_rooms')
            ->where('member_id', $member->getKey())
            ->where('room_id', $room->getKey())
            ->exists();

        if ($exists) {
            DB::table('bonfire_starred_rooms')
                ->where('member_id', $member->getKey())
                ->where('room_id', $room->getKey())
                ->delete();

            return false;
        }

        DB::table('bonfire_starred_rooms')->insert([
            'member_id' => $member->getKey(),
            'room_id' => $room->getKey(),
            'created_at' => now(),
        ]);

        return true;
    }
}
