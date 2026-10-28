<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Policies\Concerns;

use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared member-resolution for Bonfire policies.
 *
 * Policies are handed the authenticated host model (a User, bot, etc.). Bonfire
 * does not authorize the host model directly — it authorizes the Bonfire
 * Member that the host model maps to. This trait performs that mapping using
 * the package's own member-resolution helper so policies never reach into the
 * host application's namespaces.
 */
trait ResolvesMember
{
    /**
     * Resolve the active Bonfire member for an authenticated host model.
     *
     * Returns null when there is no member, or when the member is deactivated —
     * an inactive member can perform no privileged action.
     */
    protected function activeMember(Authenticatable $user): ?Member
    {
        $member = $this->memberFor($user);

        if ($member === null || ! $member->is_active) {
            return null;
        }

        return $member;
    }

    /**
     * Resolve the Bonfire member for an authenticated host model, active or not.
     */
    protected function memberFor(Authenticatable $user): ?Member
    {
        if (! $user instanceof Model) {
            return null;
        }

        return Bonfire::memberFor($user);
    }
}
