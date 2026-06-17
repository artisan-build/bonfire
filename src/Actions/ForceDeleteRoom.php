<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\Room;

/**
 * Permanently delete a room.
 *
 * The admin authorization, trashed-room lookup, null guard, and UI dispatch
 * remain in the Livewire component; this action owns only the hard-delete write.
 */
final class ForceDeleteRoom
{
    public function __invoke(Room $room): void
    {
        $room->forceDelete();
    }
}
