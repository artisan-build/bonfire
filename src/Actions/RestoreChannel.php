<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\Room;

/**
 * Restore a soft-deleted channel room.
 *
 * A room that is not trashed is a no-op. The admin authorization and trashed-room
 * lookup remain in the Livewire component; this action owns only the restore write.
 */
final class RestoreChannel
{
    public function __invoke(Room $room): void
    {
        if (! $room->trashed()) {
            return;
        }

        $room->restore();
    }
}
