<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\CallSession;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\RoomAccess;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Transition a ringing call session to active once the callee answers.
 *
 * The Livewire component keeps the transport-level guard (only on an `answer`
 * signal for a still-ringing session), host-user id resolution, and the
 * CallSignal broadcast; this action only records the status transition.
 */
final class MarkCallActive
{
    public function __invoke(CallSession $session, ?Member $member = null): void
    {
        $room = $session->room;

        if (! $room instanceof Room) {
            throw new AuthorizationException('This call has no accessible room.');
        }

        if ($member === null) {
            RoomAccess::authorizeGate($room);
        } else {
            RoomAccess::authorizeGate($room, $member);
        }

        $session->update(['status' => 'active', 'started_at' => now()]);
    }
}
