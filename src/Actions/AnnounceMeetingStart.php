<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Support\Facades\Cache;

/**
 * Record the start of a meeting: reuse or set the cached start timestamp and
 * post the "started a meeting" announcement carrying the join button. Returns
 * the start timestamp.
 *
 * The Livewire component keeps authorization and its meeting-changed dispatch.
 */
final class AnnounceMeetingStart
{
    public function __invoke(Room $room, Member $caller): int
    {
        // If an active meeting already exists, reuse its timestamp — someone may be
        // racing with us. Otherwise record a new start.
        $cacheKey = "bonfire.meeting.{$room->id}.started_at";
        $startedAt = Cache::get($cacheKey);
        if ($startedAt === null) {
            $startedAt = now()->getTimestamp();
            Cache::put($cacheKey, $startedAt, now()->addHours(6));
        }

        Message::create([
            'room_id' => $room->id,
            'member_id' => $caller->id,
            'body' => sprintf(
                '📞 <strong>%s</strong> started a meeting in <strong>#%s</strong>'
                .'<br><button type="button" data-bonfire-join-meeting data-room-id="%d" data-room-name="%s" '
                .'style="display:inline-flex;align-items:center;gap:6px;margin-top:8px;padding:8px 16px;border-radius:6px;'
                .'background:#059669;color:#ffffff;font-weight:600;font-size:13px;border:0;cursor:pointer;">'
                .'📞 Join meeting</button>',
                e($caller->display_name),
                e($room->name),
                $room->id,
                e($room->name),
            ),
        ]);

        return (int) $startedAt;
    }
}
