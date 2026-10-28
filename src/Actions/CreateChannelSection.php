<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\ChannelSection;
use ArtisanBuild\Bonfire\Models\Member;
use Illuminate\Support\Str;

/**
 * Create a channel section for a member, appended after their existing sections.
 *
 * The name is trimmed and limited to 80 characters; an empty name is a no-op and
 * returns null. The new section's position is the member's current maximum plus one.
 * The Livewire component keeps its own computed-state refresh.
 */
final class CreateChannelSection
{
    public function __invoke(Member $member, string $name): ?ChannelSection
    {
        $clean = Str::limit(trim($name), 80, '');

        if ($clean === '') {
            return null;
        }

        $position = (int) ChannelSection::query()
            ->where('member_id', $member->getKey())
            ->max('position');

        return ChannelSection::query()->create([
            'member_id' => $member->getKey(),
            'name' => $clean,
            'position' => $position + 1,
        ]);
    }
}
