<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\RoomAccess;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Soft-delete a message. The Livewire component keeps the room and
 * authorization guards; this action is a thin, container-resolvable entry point
 * for the delete so the write lives alongside the other message actions.
 */
final class DeleteMessage
{
    public function __invoke(Message $message, ?Member $member = null): void
    {
        $room = $message->room;

        if (! $room instanceof Room) {
            throw new AuthorizationException('This message has no accessible room.');
        }

        if ($member === null) {
            RoomAccess::authorizeGate($room);
        } else {
            RoomAccess::authorizeGate($room, $member);
        }

        $message->delete();
    }
}
