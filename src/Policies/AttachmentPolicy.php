<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Policies;

use ArtisanBuild\Bonfire\Models\Attachment;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Policies\Concerns\ResolvesMember;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Authorizes downloading a Bonfire Attachment.
 *
 * Access tracks the attachment's room: public rooms are readable by any active
 * member, private rooms only by their members. The streaming controller keeps
 * its own guest-friendly check for public rooms; this policy is the strict,
 * member-present variant available to callers that already require auth.
 */
class AttachmentPolicy
{
    use ResolvesMember;

    public function view(Authenticatable $user, Attachment $attachment): bool
    {
        $member = $this->activeMember($user);

        if ($member === null) {
            return false;
        }

        $attachment->loadMissing('message.room');
        $message = $attachment->message;
        $room = $message?->getRelationValue('room');

        if (! $room instanceof Room) {
            return false;
        }

        return $room->isAccessibleBy($member);
    }
}
