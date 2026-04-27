<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    #[Computed]
    public function currentMember(): ?Member
    {
        return Bonfire::memberFor(auth()->user());
    }

    /**
     * @return array<int, array{id: int, display_name: string, avatar_url: string, user_id: int}>
     */
    public function candidatesFor(int $roomId): array
    {
        $room = Room::query()->find($roomId);
        if ($room === null) {
            return [];
        }

        $me = $this->currentMember();
        $tenantId = Bonfire::tenantId();

        return Member::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when($me !== null, fn ($q) => $q->where('id', '!=', $me->id))
            ->when($room->isPrivate(), fn ($q) => $q->whereHas('rooms', fn ($r) => $r->where('bonfire_rooms.id', $room->id)))
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'avatar_url', 'memberable_id'])
            ->map(fn (Member $m) => [
                'id' => $m->id,
                'display_name' => $m->display_name,
                'avatar_url' => $m->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($m->display_name),
                'user_id' => (int) $m->memberable_id,
            ])
            ->values()
            ->all();
    }

    public function announceStart(int $roomId): ?int
    {
        $caller = $this->currentMember();
        if ($caller === null) {
            return null;
        }

        $room = Room::query()->find($roomId);
        if ($room === null) {
            return null;
        }

        // If an active meeting already exists, reuse its timestamp — someone may be
        // racing with us. Otherwise record a new start.
        $cacheKey = "bonfire.meeting.{$roomId}.started_at";
        $startedAt = Cache::get($cacheKey);
        if ($startedAt === null) {
            $startedAt = now()->getTimestamp();
            Cache::put($cacheKey, $startedAt, now()->addHours(6));
        }

        Message::create([
            'tenant_id' => $room->tenant_id,
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

        $this->dispatch('bonfire:meeting-changed');

        return (int) $startedAt;
    }

    public function getMeetingStartedAt(int $roomId): ?int
    {
        $key = "bonfire.meeting.{$roomId}.started_at";
        $value = Cache::get($key);

        if ($value === null) {
            return null;
        }

        // Drop stale cache entries — a meeting that's been "active" for more than 2
        // hours with no end-announce is almost certainly abandoned; treat it as over.
        if ((int) $value < now()->subHours(2)->getTimestamp()) {
            Cache::forget($key);

            return null;
        }

        return (int) $value;
    }

    public function announceEnd(int $roomId, int $totalParticipants): void
    {
        $caller = $this->currentMember();
        if ($caller === null) {
            return;
        }

        $room = Room::query()->find($roomId);
        if ($room === null) {
            return;
        }

        $cacheKey = "bonfire.meeting.{$roomId}.started_at";
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
            'tenant_id' => $room->tenant_id,
            'room_id' => $room->id,
            'member_id' => $caller->id,
            'body' => sprintf(
                '📞 Meeting ended · %s · %d %s',
                $duration,
                $totalParticipants,
                $totalParticipants === 1 ? 'participant' : 'participants',
            ),
        ]);

        $this->dispatch('bonfire:meeting-changed');
    }

    public function invite(int $roomId, int $memberId): void
    {
        $caller = $this->currentMember();
        if ($caller === null) {
            return;
        }

        $room = Room::query()->find($roomId);
        $target = Member::query()->find($memberId);
        if ($room === null || $target === null) {
            return;
        }

        $slug = str_replace(' ', '-', $target->display_name);
        $body = sprintf(
            '📞 <a href="#mention-%s">@%s</a> join the meeting in <strong>#%s</strong>',
            $slug,
            e($target->display_name),
            e($room->name),
        );

        Message::create([
            'tenant_id' => $room->tenant_id,
            'room_id' => $room->id,
            'member_id' => $caller->id,
            'body' => $body,
        ]);
    }
};
