<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Events\MessageEdited;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\RoomAccess;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Update a message's body and notify other viewers via MessageEdited.
 *
 * The body is expected to already be validated (non-empty) by the caller; the
 * Livewire component keeps its empty-edit no-op check and authorization.
 */
final class EditMessage
{
    public function __invoke(Message $message, string $body, ?Member $member = null): void
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

        $message->body = $body;
        $message->save();

        broadcast(new MessageEdited($message))->toOthers();
    }
}
