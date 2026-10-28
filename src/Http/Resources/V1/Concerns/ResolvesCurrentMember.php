<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Http\Resources\V1\Concerns;

use ArtisanBuild\Bonfire\Models\Member;
use Illuminate\Http\Request;

/**
 * Resolves (and memoizes for the request) the Bonfire member acting on the API.
 *
 * Resources need the current member to derive per-viewer state (whether the
 * member reacted, their poll vote, their last-read marker). Resolution goes
 * through the package's configured `resolve_member` callback so a host's bound
 * replacement is honoured, and the result is cached on the request instance so
 * a collection of N resources resolves the member once rather than N times.
 */
trait ResolvesCurrentMember
{
    protected function currentMember(Request $request): ?Member
    {
        if (! $request->attributes->has('bonfire_current_member')) {
            $resolver = config('bonfire.resolve_member');
            $member = is_callable($resolver) ? $resolver() : null;
            $request->attributes->set('bonfire_current_member', $member instanceof Member ? $member : null);
        }

        $member = $request->attributes->get('bonfire_current_member');

        return $member instanceof Member ? $member : null;
    }
}
