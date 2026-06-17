<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\Room;

/**
 * Soft-delete a channel room.
 *
 * The admin authorization, fallback-room lookup, and redirect remain in the
 * Livewire component; this action owns only the soft-delete write.
 */
final class DeleteChannel
{
    public function __invoke(Room $room): void
    {
        $room->delete();
    }
}
