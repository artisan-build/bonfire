<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\RoomAccess;

/**
 * Open or create the private direct-message room between two members.
 *
 * The room is keyed by a deterministic slug built from the two member ids sorted
 * ascending (`dm-<lowId>-<highId>`), so the same pair always resolves to the same
 * room regardless of who initiates. An existing room is reused; otherwise a private
 * room is created with both members added. The Livewire component keeps the redirect.
 */
final class OpenDirectMessage
{
    public function __invoke(Member $current, Member $target): Room
    {
        $sortedIds = collect([$current->getKey(), $target->getKey()])->sort()->implode('-');
        $slug = 'dm-'.$sortedIds;

        $room = Room::query()
            ->where('slug', $slug)
            ->first();

        if ($room === null) {
            $room = Room::query()->create([
                'name' => $target->display_name,
                'slug' => $slug,
                'type' => RoomType::Private->value,
                'created_by' => $current->getKey(),
            ]);

            $room->addMember($current);
            $room->addMember($target);
        } else {
            RoomAccess::authorizeGate($room, $current);
            RoomAccess::authorizeGate($room, $target);
        }

        return $room;
    }
}
