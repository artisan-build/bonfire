<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Support\FindOwnedScheduledMessage;
use Carbon\CarbonInterface;

/**
 * Reschedule a scheduled message's body and time.
 *
 * Only the member's own, still-future scheduled message may be updated, and a
 * new time in the past is rejected. Returns the updated message, or null when
 * the message could not be found or the new time is invalid (a no-op).
 */
final class UpdateScheduledMessage
{
    public function __invoke(Member $member, int $id, string $body, CarbonInterface $newTime): ?Message
    {
        if ($newTime->isPast()) {
            return null;
        }

        $message = resolve(FindOwnedScheduledMessage::class)($member, $id);
        if ($message === null) {
            return null;
        }

        $message->update([
            'body' => $body,
            'scheduled_for' => $newTime,
        ]);

        return $message;
    }
}
