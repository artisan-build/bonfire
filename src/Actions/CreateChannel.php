<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Support\Str;

/**
 * Create a channel room and seed its membership.
 *
 * Builds the room-type bitmask from the private/announcements flags, resolves a
 * unique slug, creates the room, and adds the creator plus any selected members
 * (skipping the creator if they appear in the selection). The Livewire component
 * keeps its own state reset, modal dispatch, and redirect.
 */
final class CreateChannel
{
    /**
     * @param  array<int, int>  $memberIds
     */
    public function __invoke(
        Member $creator,
        string $name,
        ?string $description,
        bool $private,
        bool $announcements,
        array $memberIds,
    ): Room {
        $type = 0;
        if ($private) {
            $type = RoomType::add($type, RoomType::Private);
        }
        if ($announcements) {
            $type = RoomType::add($type, RoomType::Announcements);
        }

        $room = Room::query()->create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'description' => trim((string) $description) ?: null,
            'type' => $type,
            'created_by' => $creator->getKey(),
        ]);

        $room->addMember($creator, $creator);

        foreach ($memberIds as $memberId) {
            $target = Member::query()->find($memberId);
            if ($target !== null && $target->getKey() !== $creator->getKey()) {
                $room->addMember($target, $creator);
            }
        }

        return $room;
    }

    /**
     * Resolve a slug that does not collide with an existing room.
     *
     * Produces the exact sequence the naive per-collision loop would — `base`, then
     * `base-2`, `base-3`, … (the suffix starts at 1 and is pre-incremented) — but
     * resolves it from a single query that fetches every potentially-colliding slug
     * up front instead of issuing one existence check per collision.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);

        $taken = Room::query()
            ->where(function ($query) use ($base): void {
                $query->where('slug', $base)
                    ->orWhere('slug', 'like', $base.'-%');
            })
            ->pluck('slug')
            ->flip();

        $slug = $base;
        $suffix = 1;
        while ($taken->has($slug)) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
