<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Carries SDP offers/answers and ICE candidates between the two peers in a call.
 *
 * @property array<string, mixed> $payload
 */
class CallSignal implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public int $sessionId;

    public int $targetUserId;

    public string $kind;

    /**
     * @var array<string, mixed>
     */
    public array $payload;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(int $sessionId, int $targetUserId, string $kind, array $payload)
    {
        $this->sessionId = $sessionId;
        $this->targetUserId = $targetUserId;
        $this->kind = $kind;
        $this->payload = $payload;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->targetUserId)];
    }

    public function broadcastAs(): string
    {
        return 'call.signal';
    }
}
