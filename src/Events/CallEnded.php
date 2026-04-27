<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallEnded implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public int $sessionId;

    public int $targetUserId;

    public string $reason;

    public function __construct(int $sessionId, int $targetUserId, string $reason = 'ended')
    {
        $this->sessionId = $sessionId;
        $this->targetUserId = $targetUserId;
        $this->reason = $reason;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->targetUserId)];
    }

    public function broadcastAs(): string
    {
        return 'call.ended';
    }
}
