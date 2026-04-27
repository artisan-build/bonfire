<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a reaction is added or removed from a message.
 */
class ReactionToggled implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $messageId,
        public int $roomId,
        public int $memberId,
        public int $count,
        public bool $active,
    ) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel('bonfire.room.'.$this->roomId)];
    }

    public function broadcastAs(): string
    {
        return 'reaction.toggled';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'member_id' => $this->memberId,
            'count' => $this->count,
            'active' => $this->active,
        ];
    }
}
