<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Policies;

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Policies\Concerns\ResolvesMember;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Authorizes actions against a Bonfire Room.
 *
 * Every ability resolves the acting Bonfire member from the authenticated host
 * model first; an inactive or non-existent member is denied outright.
 */
class RoomPolicy
{
    use ResolvesMember;

    /**
     * View a room. Public rooms are visible to any active member; private rooms
     * only to their members. Mirrors Room::isAccessibleBy().
     */
    public function view(Authenticatable $user, Room $room): bool
    {
        $member = $this->activeMember($user);

        if ($member === null) {
            return false;
        }

        return $room->isAccessibleBy($member);
    }

    /**
     * Post a message in a room. Archived rooms are read-only; announcement rooms
     * require at least Moderator; otherwise the room must be accessible.
     */
    public function post(Authenticatable $user, Room $room): bool
    {
        $member = $this->activeMember($user);

        if ($member === null) {
            return false;
        }

        if ($room->isArchived()) {
            return false;
        }

        if ($room->isAnnouncements()) {
            return $member->hasRoleAtLeast(BonfireRole::Moderator)
                && $room->isAccessibleBy($member);
        }

        return $room->isAccessibleBy($member);
    }

    /**
     * Create a new room. Any active member may create one.
     */
    public function create(Authenticatable $user): bool
    {
        return $this->activeMember($user) !== null;
    }

    /**
     * Forward a message into a target room. The target must not be archived, and
     * private targets require membership.
     */
    public function forwardInto(Authenticatable $user, Room $room): bool
    {
        $member = $this->activeMember($user);

        if ($member === null) {
            return false;
        }

        if ($room->isArchived()) {
            return false;
        }

        return $room->isAccessibleBy($member);
    }

    /**
     * Administer a room (rename, retype, delete, restore, force-delete) and its
     * membership roster. Reserved for admins.
     */
    public function manage(Authenticatable $user): bool
    {
        $member = $this->activeMember($user);

        return $member !== null && $member->hasRoleAtLeast(BonfireRole::Admin);
    }

    /**
     * Start, end, or invite to a meeting/call in a room. Requires room access;
     * archived rooms are excluded.
     */
    public function useRealtime(Authenticatable $user, Room $room): bool
    {
        $member = $this->activeMember($user);

        if ($member === null) {
            return false;
        }

        if ($room->isArchived()) {
            return false;
        }

        return $room->isAccessibleBy($member);
    }
}
