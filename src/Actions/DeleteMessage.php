<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\Message;

/**
 * Soft-delete a message. The Livewire component keeps the room and
 * authorization guards; this action is a thin, container-resolvable entry point
 * for the delete so the write lives alongside the other message actions.
 */
final class DeleteMessage
{
    public function __invoke(Message $message): void
    {
        $message->delete();
    }
}
