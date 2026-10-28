<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Support\FindOwnedScheduledMessage;

/**
 * Release a scheduled message immediately: clear scheduled_for and stamp
 * created_at to now.
 *
 * Only the member's own, still-future scheduled message may be sent now.
 * Returns the updated message, or null when no such message exists (a no-op).
 */
final class SendScheduledMessageNow
{
    public function __invoke(Member $member, int $id): ?Message
    {
        $message = resolve(FindOwnedScheduledMessage::class)($member, $id);
        if ($message === null) {
            return null;
        }

        $message->update([
            'scheduled_for' => null,
            'created_at' => now(),
        ]);

        return $message;
    }
}
