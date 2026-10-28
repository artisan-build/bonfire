<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Events\MessageEdited;
use ArtisanBuild\Bonfire\Models\Message;

/**
 * Update a message's body and notify other viewers via MessageEdited.
 *
 * The body is expected to already be validated (non-empty) by the caller; the
 * Livewire component keeps its empty-edit no-op check and authorization.
 */
final class EditMessage
{
    public function __invoke(Message $message, string $body): void
    {
        $message->body = $body;
        $message->save();

        broadcast(new MessageEdited($message))->toOthers();
    }
}
