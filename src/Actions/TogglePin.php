<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Events\MessageEdited;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\RoomAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

/**
 * Pin or unpin a message. Pinning records both the pin timestamp and the member
 * who pinned it; unpinning clears both. The MessageEdited broadcast notifies
 * other viewers so their pin state refreshes.
 *
 * The Livewire component keeps authorization and its own state refresh.
 */
final class TogglePin
{
    public function __invoke(Member $member, Message $message): void
    {
        $room = $message->room;

        if (! $room instanceof Room) {
            throw new AuthorizationException('This message has no accessible room.');
        }

        RoomAccess::authorizeGate($room, $member);

        if ($message->pinned_at === null) {
            $message->pinned_at = Carbon::now();
            $message->pinned_by_member_id = $member->getKey();
        } else {
            $message->pinned_at = null;
            $message->pinned_by_member_id = null;
        }
        $message->save();

        broadcast(new MessageEdited($message))->toOthers();
    }
}
