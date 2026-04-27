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
 * Fired when an existing message's body is edited.
 */
class MessageEdited implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Message $message) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel('bonfire.room.'.$this->message->room_id)];
    }

    public function broadcastAs(): string
    {
        return 'message.edited';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'room_id' => $this->message->room_id,
            'parent_id' => $this->message->parent_id,
            'member_id' => $this->message->member_id,
        ];
    }
}
