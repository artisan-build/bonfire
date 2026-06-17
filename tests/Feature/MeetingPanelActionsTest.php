<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Actions\AnnounceMeetingEnd;
use ArtisanBuild\Bonfire\Actions\AnnounceMeetingStart;
use ArtisanBuild\Bonfire\Actions\InviteToMeeting;
use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

function meetingActionMember(string $name, BonfireRole $role = BonfireRole::Member): Member
{
    $user = TestUser::query()->create(['name' => $name]);

    return Bonfire::ensureMember($user, $name, null, $role);
}

function meetingActionRoom(Member $creator): Room
{
    return Room::query()->create([
        'name' => 'Room '.Str::random(6),
        'slug' => Str::slug(Str::random(8)),
        'type' => 0,
        'created_by' => $creator->getKey(),
    ]);
}

it('caches the start timestamp, posts the join-button message, and returns the timestamp', function (): void {
    $caller = meetingActionMember('Ada');
    $room = meetingActionRoom($caller);

    $startedAt = app(AnnounceMeetingStart::class)($room, $caller);

    expect($startedAt)->toBeInt()
        ->and(Cache::get("bonfire.meeting.{$room->id}.started_at"))->toBe($startedAt);

    $message = Message::query()->where('room_id', $room->id)->latest('id')->first();
    expect($message)->not->toBeNull()
        ->and($message->body)->toContain('started a meeting')
        ->and($message->body)->toContain('data-bonfire-join-meeting')
        ->and($message->body)->toContain('Join meeting');
});

it('reuses an existing cached start timestamp on a second announce', function (): void {
    $caller = meetingActionMember('Ada');
    $room = meetingActionRoom($caller);

    $first = app(AnnounceMeetingStart::class)($room, $caller);
    $second = app(AnnounceMeetingStart::class)($room, $caller);

    expect($second)->toBe($first)
        ->and(Cache::get("bonfire.meeting.{$room->id}.started_at"))->toBe($first);
});

it('ends the meeting, forgets the cache, strips the join button, and posts a summary', function (): void {
    $caller = meetingActionMember('Ada');
    $room = meetingActionRoom($caller);

    app(AnnounceMeetingStart::class)($room, $caller);

    app(AnnounceMeetingEnd::class)($room, $caller, 2);

    expect(Cache::get("bonfire.meeting.{$room->id}.started_at"))->toBeNull();

    $startMessage = Message::query()
        ->where('room_id', $room->id)
        ->where('body', 'like', '%started a meeting%')
        ->first();

    expect($startMessage->body)->not->toContain('data-bonfire-join-meeting')
        ->and($startMessage->body)->toContain('Meeting ended');

    $summary = Message::query()->where('room_id', $room->id)->latest('id')->first();
    expect($summary->body)->toContain('Meeting ended')
        ->and($summary->body)->toContain('2 participants');
});

it('uses the singular participant label for a single participant', function (): void {
    $caller = meetingActionMember('Ada');
    $room = meetingActionRoom($caller);

    app(AnnounceMeetingStart::class)($room, $caller);
    app(AnnounceMeetingEnd::class)($room, $caller, 1);

    $summary = Message::query()->where('room_id', $room->id)->latest('id')->first();
    expect($summary->body)->toContain('1 participant')
        ->and($summary->body)->not->toContain('1 participants');
});

it('posts an invite message mentioning the target member', function (): void {
    $caller = meetingActionMember('Ada');
    $target = meetingActionMember('Grace Hopper');
    $room = meetingActionRoom($caller);

    app(InviteToMeeting::class)($room, $caller, $target);

    $message = Message::query()->where('room_id', $room->id)->latest('id')->first();
    expect($message)->not->toBeNull()
        ->and($message->member_id)->toBe($caller->id)
        ->and($message->body)->toContain('join the meeting')
        ->and($message->body)->toContain('Grace Hopper')
        ->and($message->body)->toContain('#mention-Grace-Hopper');
});
