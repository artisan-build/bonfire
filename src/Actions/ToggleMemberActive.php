<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;

/**
 * Flip a member's active state: deactivate when currently active, reactivate
 * otherwise.
 *
 * The admin authorization, member lookup, and state refresh remain in the
 * Livewire component; this action owns only the activation write.
 */
final class ToggleMemberActive
{
    public function __invoke(Member $member): void
    {
        if ($member->is_active) {
            Bonfire::deactivate($member);
        } else {
            Bonfire::reactivate($member);
        }
    }
}
