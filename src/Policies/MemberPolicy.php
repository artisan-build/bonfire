<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Policies;

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Policies\Concerns\ResolvesMember;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Authorizes administrative actions against Bonfire Members
 * (role changes, activation toggles, removal, room-roster edits).
 *
 * All of these are admin-only.
 */
class MemberPolicy
{
    use ResolvesMember;

    /**
     * Administer members in general (used for the admin panel and roster edits
     * where no single target member is in play).
     */
    public function administer(Authenticatable $user): bool
    {
        $actor = $this->activeMember($user);

        return $actor !== null && $actor->hasRoleAtLeast(BonfireRole::Admin);
    }

    /**
     * Change a member's role. Admin-only.
     */
    public function changeRole(Authenticatable $user, Member $target): bool
    {
        return $this->administer($user);
    }

    /**
     * Activate or deactivate a member. Admin-only.
     */
    public function toggleActive(Authenticatable $user, Member $target): bool
    {
        return $this->administer($user);
    }

    /**
     * Remove (kick) a member. Admin-only, and an admin may not kick themselves.
     */
    public function kick(Authenticatable $user, Member $target): bool
    {
        $actor = $this->activeMember($user);

        if ($actor === null || ! $actor->hasRoleAtLeast(BonfireRole::Admin)) {
            return false;
        }

        return $actor->getKey() !== $target->getKey();
    }
}
