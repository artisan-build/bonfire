<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\SafeMessageHtml;

/**
 * Post a meeting invitation mentioning the target member into a room.
 *
 * The Livewire component keeps authorization, room/target resolution, and its
 * dispatch; this action only builds and posts the invite message.
 */
final class InviteToMeeting
{
    public function __invoke(Room $room, Member $caller, Member $target): void
    {
        $body = sprintf(
            '📞 %s join the meeting in <strong>#%s</strong>',
            SafeMessageHtml::mention($target->display_name),
            SafeMessageHtml::escape($room->name),
        );

        Message::create([
            'room_id' => $room->id,
            'member_id' => $caller->id,
            'body' => $body,
        ]);
    }
}
