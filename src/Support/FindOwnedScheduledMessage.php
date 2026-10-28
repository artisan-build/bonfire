<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Support;

use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Locate a single scheduled message that belongs to the given member and is
 * still due in the future. Shared by the scheduled-message lifecycle actions
 * (update/cancel/send-now) and the composer's read-only computed property so
 * the "own, still-future scheduled message" rule lives in exactly one place.
 */
final class FindOwnedScheduledMessage
{
    public function __invoke(Member $member, int $id): ?Message
    {
        $message = Message::query()
            ->with('room')
            ->where('id', $id)
            ->where('member_id', $member->getKey())
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '>', now())
            ->first();

        if ($message === null) {
            return null;
        }

        $room = $message->getRelationValue('room');

        if (! $room instanceof Room) {
            return null;
        }

        try {
            RoomAccess::authorizeGate($room, $member);
        } catch (AuthorizationException) {
            return null;
        }

        return $message;
    }
}
