<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Support\FindOwnedScheduledMessage;

/**
 * Cancel (soft-delete) a scheduled message.
 *
 * Only the member's own, still-future scheduled message may be cancelled.
 * Returns the deleted message, or null when no such message exists (a no-op).
 */
final class CancelScheduledMessage
{
    public function __invoke(Member $member, int $id): ?Message
    {
        $message = resolve(FindOwnedScheduledMessage::class)($member, $id);
        if ($message === null) {
            return null;
        }

        $message->delete();

        return $message;
    }
}
