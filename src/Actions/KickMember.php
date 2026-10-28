<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\Member;

/**
 * Kick a member: detach them from every room, then delete the member.
 *
 * The admin authorization, member lookup, null guard, and UI dispatch remain in
 * the Livewire component; this action owns only the detach-then-delete write.
 */
final class KickMember
{
    public function __invoke(Member $member): void
    {
        $member->rooms()->detach();
        $member->delete();
    }
}
