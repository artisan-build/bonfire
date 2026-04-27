<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Events;

use ArtisanBuild\Bonfire\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a message is soft-deleted.
 */
class MessageDeleted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public int $messageId, public int $roomId) {}

    public static function forMessage(Message $message): self
    {
        return new self($message->id, $message->room_id);
    }

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel('bonfire.room.'.$this->roomId)];
    }

    public function broadcastAs(): string
    {
        return 'message.deleted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->messageId,
            'room_id' => $this->roomId,
        ];
    }
}
