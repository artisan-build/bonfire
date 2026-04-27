<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Support;

use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Session;

/**
 * Tracks last-read timestamps for rooms, session-backed for public rooms and
 * pivot-backed for private rooms.
 */
class UnreadTracker
{
    private const string SESSION_KEY = 'bonfire.last_read';

    public function markRead(Room $room, ?Member $member): void
    {
        $timestamp = now();

        if ($room->isPrivate() && $member !== null) {
            $room->members()->updateExistingPivot($member->getKey(), [
                'last_read_at' => $timestamp,
            ]);

            return;
        }

        $store = $this->sessionStore();
        $store[$room->getKey()] = $timestamp->toIso8601String();
        Session::put(self::SESSION_KEY, $store);
    }

    public function lastReadAt(Room $room, ?Member $member): ?CarbonInterface
    {
        if ($room->isPrivate() && $member !== null) {
            $pivot = $room->members()
                ->whereKey($member->getKey())
                ->first()?->pivot;

            $value = $pivot?->getAttribute('last_read_at');

            if ($value === null) {
                return null;
            }

            return $value instanceof CarbonInterface ? $value : Date::parse((string) $value);
        }

        $stored = $this->sessionStore()[$room->getKey()] ?? null;

        return $stored === null ? null : Date::parse($stored);
    }

    public function hasUnread(Room $room, ?Member $member): bool
    {
        $lastRead = $this->lastReadAt($room, $member);

        $query = Message::query()
            ->where('room_id', $room->getKey())
            ->whereNull('parent_id');

        if ($member !== null) {
            $query->where('member_id', '!=', $member->getKey());
        }

        if ($lastRead !== null) {
            $query->where('created_at', '>', $lastRead);
        }

        return $query->exists();
    }

    /**
     * @return array<int, string>
     */
    private function sessionStore(): array
    {
        $value = Session::get(self::SESSION_KEY, []);

        return is_array($value) ? $value : [];
    }
}
