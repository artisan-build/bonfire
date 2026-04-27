<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Events;

use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a workspace member is @-mentioned in a message.
 */
class MemberMentioned implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Member $mentioned,
        public Member $author,
        public Message $message,
        public Room $room,
    ) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        $userType = $this->mentioned->memberable_type;
        $userId = $this->mentioned->memberable_id;

        if ($userType === null || $userId === null) {
            return [];
        }

        return [new PrivateChannel(str_replace('\\', '.', $userType).'.'.$userId)];
    }

    public function broadcastAs(): string
    {
        return 'member.mentioned';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->id,
            'room_id' => $this->room->id,
            'room_slug' => $this->room->slug,
            'room_name' => $this->room->name,
            'author_name' => $this->author->display_name,
            'author_avatar' => $this->author->avatar_url,
            'preview' => mb_substr(strip_tags($this->message->body), 0, 140),
        ];
    }
}
