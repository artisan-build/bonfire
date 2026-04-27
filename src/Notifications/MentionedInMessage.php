<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Notifications;

use ArtisanBuild\Bonfire\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifies a Bonfire member that they were mentioned in a message.
 */
class MentionedInMessage extends Notification
{
    use Queueable;

    public function __construct(public Message $message) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = config('bonfire.notification_channels', ['database']);

        return is_array($channels) ? array_values($channels) : ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'message_id' => $this->message->id,
            'room_id' => $this->message->room_id,
            'member_id' => $this->message->member_id,
            'body' => $this->message->body,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    public function toBroadcast(mixed $notifiable): mixed
    {
        return $this->toArray($notifiable);
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You were mentioned in Bonfire')
            ->line('You were mentioned in a message.')
            ->line($this->message->body);
    }
}
