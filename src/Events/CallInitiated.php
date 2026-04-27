<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Events;

use ArtisanBuild\Bonfire\Models\CallSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallInitiated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public int $sessionId;

    public int $roomId;

    public int $callerMemberId;

    public string $callerName;

    public ?string $callerAvatar;

    public int $targetUserId;

    public function __construct(CallSession $session, string $callerName, ?string $callerAvatar, int $targetUserId)
    {
        $this->sessionId = $session->id;
        $this->roomId = $session->room_id;
        $this->callerMemberId = $session->caller_member_id;
        $this->callerName = $callerName;
        $this->callerAvatar = $callerAvatar;
        $this->targetUserId = $targetUserId;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->targetUserId)];
    }

    public function broadcastAs(): string
    {
        return 'call.initiated';
    }
}
