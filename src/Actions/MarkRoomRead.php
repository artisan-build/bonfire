<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\UnreadTracker;

/**
 * Marks a room as read for a member, recording the current timestamp as their
 * last-read marker. Wraps the UnreadTracker so components depend on a single,
 * container-resolvable entry point rather than the tracker's storage details.
 *
 * The tracker is resolved from the container (rather than constructor-injected)
 * so the host application's bound replacement is honoured without coupling this
 * action to a concrete tracker type.
 */
final class MarkRoomRead
{
    public function __invoke(Room $room, ?Member $member): void
    {
        resolve(UnreadTracker::class)->markRead($room, $member);
    }
}
