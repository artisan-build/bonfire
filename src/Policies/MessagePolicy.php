<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Policies;

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Policies\Concerns\ResolvesMember;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Authorizes actions against a Bonfire Message.
 *
 * Read/interaction abilities are gated on access to the message's room, so a
 * non-member of a private room can neither see nor act on its messages even
 * when they hold a raw message id.
 */
class MessagePolicy
{
    use ResolvesMember;

    /**
     * Delete a message. The author may delete their own; moderators and admins
     * may delete anyone's.
     */
    public function delete(Authenticatable $user, Message $message): bool
    {
        $member = $this->activeMember($user);

        if ($member === null) {
            return false;
        }

        if ($message->member_id === $member->getKey()) {
            return true;
        }

        return $member->hasRoleAtLeast(BonfireRole::Moderator);
    }

    /**
     * Edit a message. Only the author may edit, and not once it is trashed.
     */
    public function update(Authenticatable $user, Message $message): bool
    {
        $member = $this->activeMember($user);

        if ($member === null || $message->trashed()) {
            return false;
        }

        return $message->member_id === $member->getKey();
    }

    /**
     * Pin or unpin a message. Requires access to the message's room and a
     * non-trashed message.
     */
    public function pin(Authenticatable $user, Message $message): bool
    {
        $member = $this->activeMember($user);

        if ($member === null || $message->trashed()) {
            return false;
        }

        $room = $message->room;

        return $room instanceof Room && $room->isAccessibleBy($member);
    }

    /**
     * React to a message. Requires access to the message's room.
     */
    public function react(Authenticatable $user, Message $message): bool
    {
        $member = $this->activeMember($user);

        if ($member === null) {
            return false;
        }

        $room = $message->room;

        return $room instanceof Room && $room->isAccessibleBy($member);
    }

    /**
     * Vote on a poll message. Requires access to the message's room.
     */
    public function vote(Authenticatable $user, Message $message): bool
    {
        return $this->react($user, $message);
    }
}
