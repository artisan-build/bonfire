<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Http\Controllers\Api\V1;

use ArtisanBuild\Bonfire\Models\Member;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Base for the Bonfire HTTP/JSON API v1 controllers.
 *
 * Brings in {@see AuthorizesRequests} so actions authorize through the package's
 * registered policies (which resolve the acting member from the host user), and
 * centralizes resolution of the acting Bonfire member via the configurable
 * `bonfire.resolve_member` callback.
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;

    /**
     * The Bonfire member acting on this request, resolved via the host's
     * configured callback (defaults to the member mapped from Auth::user()).
     */
    protected function actingMember(): ?Member
    {
        $resolver = config('bonfire.resolve_member');
        $member = is_callable($resolver) ? $resolver() : null;

        return $member instanceof Member ? $member : null;
    }
}
