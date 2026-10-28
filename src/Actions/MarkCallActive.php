<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\CallSession;

/**
 * Transition a ringing call session to active once the callee answers.
 *
 * The Livewire component keeps the transport-level guard (only on an `answer`
 * signal for a still-ringing session), host-user id resolution, and the
 * CallSignal broadcast; this action only records the status transition.
 */
final class MarkCallActive
{
    public function __invoke(CallSession $session): void
    {
        $session->update(['status' => 'active', 'started_at' => now()]);
    }
}
