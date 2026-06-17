<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\UnreadTracker;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

function unreadMember(string $name, BonfireRole $role = BonfireRole::Member): Member
{
    $user = TestUser::query()->create(['name' => $name]);

    return Bonfire::ensureMember($user, $name, null, $role);
}

function unreadRoom(string $name, int $type, Member $creator): Room
{
    return Room::query()->create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::random(4),
        'type' => $type,
        'created_by' => $creator->getKey(),
    ]);
}

function unreadPost(Room $room, Member $member): Message
{
    return Message::query()->create([
        'room_id' => $room->getKey(),
        'member_id' => $member->getKey(),
        'body' => 'Hello',
    ]);
}

it('persists the public-room read marker in the database, not the session', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-06-17 10:00:00'));

    $admin = unreadMember('Admin', BonfireRole::Admin);
    $memberA = unreadMember('Ada');
    $room = unreadRoom('General', 0, $admin);

    $tracker = new UnreadTracker;
    $tracker->markRead($room, $memberA);

    // Simulate a fresh session / different device: nothing survives in session.
    Session::flush();

    $result = $tracker->lastReadAt($room, $memberA);

    expect($result)->not->toBeNull()
        ->and($result->equalTo(Date::parse('2026-06-17 10:00:00')))->toBeTrue();
});

it('isolates read markers per member', function (): void {
    $admin = unreadMember('Admin', BonfireRole::Admin);
    $memberA = unreadMember('Ada');
    $memberB = unreadMember('Bea');
    $room = unreadRoom('General', 0, $admin);

    $tracker = new UnreadTracker;
    $tracker->markRead($room, $memberA);

    expect($tracker->lastReadAt($room, $memberB))->toBeNull();
});

it('updates the existing row on a second read rather than duplicating it', function (): void {
    $admin = unreadMember('Admin', BonfireRole::Admin);
    $memberA = unreadMember('Ada');
    $room = unreadRoom('General', 0, $admin);

    $tracker = new UnreadTracker;
    $tracker->markRead($room, $memberA);
    $tracker->markRead($room, $memberA);

    $rows = DB::table('bonfire_room_reads')
        ->where('member_id', $memberA->getKey())
        ->where('room_id', $room->getKey())
        ->count();

    expect($rows)->toBe(1);
});

it('writes the pivot for private rooms and never touches bonfire_room_reads', function (): void {
    $admin = unreadMember('Admin', BonfireRole::Admin);
    $member = unreadMember('Ada');
    $room = unreadRoom('Secret', RoomType::Private->value, $admin);
    $room->addMember($member);

    $tracker = new UnreadTracker;
    $tracker->markRead($room, $member);

    expect($tracker->lastReadAt($room, $member))->not->toBeNull()
        ->and(DB::table('bonfire_room_reads')->count())->toBe(0);
});

it('reports unread when another member posts after the last read', function (): void {
    $base = CarbonImmutable::parse('2026-06-17 12:00:00');

    $admin = unreadMember('Admin', BonfireRole::Admin);
    $author = unreadMember('Author');
    $reader = unreadMember('Reader');
    $room = unreadRoom('General', 0, $admin);

    $tracker = new UnreadTracker;

    $this->travelTo($base);
    unreadPost($room, $author);

    $this->travelTo($base->addMinute());
    $tracker->markRead($room, $reader);

    expect($tracker->hasUnread($room, $reader))->toBeFalse();

    $this->travelTo($base->addMinutes(2));
    unreadPost($room, $author);

    expect($tracker->hasUnread($room, $reader))->toBeTrue();
});
