<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Support\Facades\Cache;

/**
 * Conclude a meeting: forget the cached start timestamp, strip the join button
 * from the most recent start announcement, and post the "Meeting ended" summary
 * with duration and participant count.
 *
 * The Livewire component keeps authorization and its meeting-changed dispatch.
 */
final class AnnounceMeetingEnd
{
    public function __invoke(Room $room, Member $caller, int $totalParticipants): void
    {
        $cacheKey = "bonfire.meeting.{$room->id}.started_at";
        $startedAt = Cache::get($cacheKey);
        Cache::forget($cacheKey);

        $durationSeconds = $startedAt !== null
            ? max(0, now()->getTimestamp() - (int) $startedAt)
            : 0;

        $m = intdiv($durationSeconds, 60);
        $s = $durationSeconds % 60;
        $duration = sprintf('%d:%02d', $m, $s);

        // Strip the join button from the most recent "started a meeting" message so
        // nobody clicks it expecting to rejoin the concluded call.
        $startMessage = Message::query()
            ->where('room_id', $room->id)
            ->where('body', 'like', '%data-bonfire-join-meeting%')
            ->where('created_at', '>=', now()->subHours(6))
            ->latest()
            ->first();

        if ($startMessage !== null) {
            $cleaned = preg_replace(
                '/<br>\s*<button[^>]*data-bonfire-join-meeting[^>]*>.*?<\/button>/s',
                '<br><span style="display:inline-block;margin-top:6px;padding:4px 10px;border-radius:6px;background:#3f3f46;color:#a1a1aa;font-size:12px;">Meeting ended</span>',
                $startMessage->body,
            );
            if ($cleaned !== null && $cleaned !== $startMessage->body) {
                $startMessage->update(['body' => $cleaned]);
            }
        }

        Message::create([
            'room_id' => $room->id,
            'member_id' => $caller->id,
            'body' => sprintf(
                '📞 Meeting ended · %s · %d %s',
                $duration,
                $totalParticipants,
                $totalParticipants === 1 ? 'participant' : 'participants',
            ),
        ]);
    }
}
