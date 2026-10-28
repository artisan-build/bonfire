<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\CallSession;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\RoomAccess;

/**
 * Persist a new ringing call session between a caller and callee in a room.
 *
 * The Livewire component keeps authorization, the callee room-membership guard,
 * host-user id resolution, and the CallInitiated broadcast; this action only
 * records the package-side session row.
 */
final class InitiateCall
{
    public function __invoke(Room $room, Member $caller, Member $callee): CallSession
    {
        RoomAccess::authorizeGate($room, $caller);
        RoomAccess::authorizeGate($room, $callee);

        return CallSession::query()->create([
            'room_id' => $room->id,
            'caller_member_id' => $caller->id,
            'callee_member_id' => $callee->id,
            'status' => 'ringing',
        ]);
    }
}
