<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\ChannelSection;
use ArtisanBuild\Bonfire\Models\Member;
use Illuminate\Support\Facades\DB;

/**
 * Assign (or unassign) a room to one of the member's channel sections.
 *
 * When the member has no pivot row for the room yet, one is inserted carrying the
 * section assignment — public rooms aren't formally joined, but the assignment is
 * per-user sidebar state. When a pivot already exists, the section must belong to
 * the member (or be null to clear); an invalid/foreign section is a no-op.
 */
final class AssignRoomToSection
{
    public function __invoke(Member $member, int $roomId, ?int $sectionId): void
    {
        $pivotExists = DB::table('bonfire_member_room')
            ->where('member_id', $member->getKey())
            ->where('room_id', $roomId)
            ->exists();

        if (! $pivotExists) {
            DB::table('bonfire_member_room')->insert([
                'member_id' => $member->getKey(),
                'room_id' => $roomId,
                'section_id' => $sectionId,
                'created_by' => $member->getKey(),
                'created_at' => now(),
            ]);

            return;
        }

        $validSection = $sectionId === null || ChannelSection::query()
            ->where('member_id', $member->getKey())
            ->where('id', $sectionId)
            ->exists();

        if (! $validSection) {
            return;
        }

        DB::table('bonfire_member_room')
            ->where('member_id', $member->getKey())
            ->where('room_id', $roomId)
            ->update(['section_id' => $sectionId]);
    }
}
