<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\ChannelSection;
use ArtisanBuild\Bonfire\Models\Member;
use Illuminate\Support\Str;

/**
 * Rename a member's own channel section.
 *
 * The name is trimmed and limited to 80 characters; an empty name is a no-op. The
 * update is scoped to the member so a member can only rename their own sections.
 */
final class RenameChannelSection
{
    public function __invoke(Member $member, int $sectionId, string $name): void
    {
        $clean = Str::limit(trim($name), 80, '');

        if ($clean === '') {
            return;
        }

        ChannelSection::query()
            ->where('member_id', $member->getKey())
            ->where('id', $sectionId)
            ->update(['name' => $clean]);
    }
}
