<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Events\MessagePosted;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\RoomAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Convert scheduled messages whose scheduled_for has come due into regular
 * posted messages: clear scheduled_for, back-date created_at to the original
 * scheduled time, and fire MessagePosted for each so listeners refresh.
 *
 * The scope is parameterised so the same action serves both the composer's
 * member-scoped release and a room-scoped release. At least one of $member or
 * $room must be supplied — calling with neither would release every due
 * scheduled message application-wide, which is never an intended UI action and
 * is rejected with an InvalidArgumentException. Passing a member, a room, or
 * both narrows the set of messages released; the released collection is
 * returned so callers can decide whether to surface a UI event.
 */
final class ReleaseDueScheduledMessages
{
    /**
     * @return Collection<int, Message> the messages that were released
     *
     * @throws InvalidArgumentException when neither a member nor a room scope is given
     */
    public function __invoke(?Member $member = null, ?Room $room = null): Collection
    {
        if ($member === null && $room === null) {
            throw new InvalidArgumentException('A member or room scope is required to release due scheduled messages.');
        }

        $query = Message::query()
            ->with(['member', 'room'])
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now());

        if ($member !== null) {
            $query->where('member_id', $member->getKey());
        }

        if ($room !== null) {
            $query->where('room_id', $room->getKey());
        }

        $due = $query->get()->filter(function (Message $message) use ($member): bool {
            $author = $member ?? $message->getRelationValue('member');
            $messageRoom = $message->getRelationValue('room');

            if (! $author instanceof Member || ! $messageRoom instanceof Room) {
                return false;
            }

            try {
                RoomAccess::authorizeGate($messageRoom, $author);
            } catch (AuthorizationException) {
                return false;
            }

            return true;
        })->values();

        foreach ($due as $message) {
            $scheduledAt = $message->scheduled_for;
            $message->forceFill([
                'scheduled_for' => null,
                'created_at' => $scheduledAt ?? now(),
            ])->save();

            event(new MessagePosted($message));
        }

        return $due;
    }
}
