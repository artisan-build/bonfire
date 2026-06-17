<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Actions\AnnounceMeetingEnd;
use ArtisanBuild\Bonfire\Actions\AnnounceMeetingStart;
use ArtisanBuild\Bonfire\Actions\InviteToMeeting;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
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

        return Member::query()
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

        $this->authorize('useRealtime', $room);

        $startedAt = app(AnnounceMeetingStart::class)($room, $caller);

        $this->dispatch('bonfire:meeting-changed');

        return $startedAt;
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

        $this->authorize('useRealtime', $room);

        app(AnnounceMeetingEnd::class)($room, $caller, $totalParticipants);

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

        $this->authorize('useRealtime', $room);

        app(InviteToMeeting::class)($room, $caller, $target);
    }
};
