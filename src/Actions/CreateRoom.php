<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Support\Str;

/**
 * Create a room from the admin panel.
 *
 * Builds the room-type bitmask from the private/archived/announcements flags and
 * assigns a slug of `slug-<random6>` (always a random suffix — distinct from the
 * channel-creation flow, which de-duplicates by increment). The Livewire component
 * keeps its empty-name guard, authorization, actor resolution, state reset, and
 * UI dispatch.
 */
final class CreateRoom
{
    public function __invoke(
        Member $creator,
        string $name,
        ?string $description,
        bool $private,
        bool $archived,
        bool $announcements,
    ): Room {
        $type = 0;
        if ($private) {
            $type = RoomType::add($type, RoomType::Private);
        }
        if ($archived) {
            $type = RoomType::add($type, RoomType::Archived);
        }
        if ($announcements) {
            $type = RoomType::add($type, RoomType::Announcements);
        }

        return Room::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => trim((string) $description) ?: null,
            'type' => $type,
            'created_by' => $creator->getKey(),
        ]);
    }
}
