<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\ChannelSection;
use ArtisanBuild\Bonfire\Models\Member;
use Illuminate\Support\Facades\DB;

/**
 * Delete a member's own channel section.
 *
 * Rooms the member had assigned to the section have their pivot `section_id`
 * nulled before the section is removed. Scoped to the member so a member can only
 * delete their own sections; an unknown section is a no-op.
 */
final class DeleteChannelSection
{
    public function __invoke(Member $member, int $sectionId): void
    {
        $section = ChannelSection::query()
            ->where('member_id', $member->getKey())
            ->where('id', $sectionId)
            ->first();

        if ($section === null) {
            return;
        }

        DB::table('bonfire_member_room')
            ->where('member_id', $member->getKey())
            ->where('section_id', $sectionId)
            ->update(['section_id' => null]);

        $section->delete();
    }
}
