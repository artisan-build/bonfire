<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Actions\EndCall;
use ArtisanBuild\Bonfire\Actions\InitiateCall;
use ArtisanBuild\Bonfire\Actions\MarkCallActive;
use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\CallSession;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Str;

function callActionMember(string $name, BonfireRole $role = BonfireRole::Member): Member
{
    $user = TestUser::query()->create(['name' => $name]);

    return Bonfire::ensureMember($user, $name, null, $role);
}

function callActionRoom(Member $creator): Room
{
    return Room::query()->create([
        'name' => 'Room '.Str::random(6),
        'slug' => Str::slug(Str::random(8)),
        'type' => 0,
        'created_by' => $creator->getKey(),
    ]);
}

it('creates a ringing call session for the caller and callee', function (): void {
    $caller = callActionMember('Caller');
    $callee = callActionMember('Callee');
    $room = callActionRoom($caller);

    $session = app(InitiateCall::class)($room, $caller, $callee);

    expect($session)->toBeInstanceOf(CallSession::class)
        ->and($session->room_id)->toBe($room->id)
        ->and($session->caller_member_id)->toBe($caller->id)
        ->and($session->callee_member_id)->toBe($callee->id)
        ->and($session->status)->toBe('ringing')
        ->and($session->exists)->toBeTrue();
});

it('flips a ringing session to active and records the start time', function (): void {
    $caller = callActionMember('Caller');
    $callee = callActionMember('Callee');
    $room = callActionRoom($caller);

    $session = CallSession::query()->create([
        'room_id' => $room->id,
        'caller_member_id' => $caller->id,
        'callee_member_id' => $callee->id,
        'status' => 'ringing',
    ]);

    app(MarkCallActive::class)($session);

    $fresh = $session->fresh();
    expect($fresh->status)->toBe('active')
        ->and($fresh->started_at)->not->toBeNull();
});

it('ends an active call, sets ended_at, and posts a call-ended summary', function (): void {
    $caller = callActionMember('Caller');
    $callee = callActionMember('Callee');
    $room = callActionRoom($caller);

    $session = CallSession::query()->create([
        'room_id' => $room->id,
        'caller_member_id' => $caller->id,
        'callee_member_id' => $callee->id,
        'status' => 'active',
        'started_at' => now()->subSeconds(75),
    ]);

    app(EndCall::class)($session, $caller, 'ended');

    $fresh = $session->fresh();
    expect($fresh->status)->toBe('ended')
        ->and($fresh->ended_at)->not->toBeNull();

    $summary = Message::query()->where('room_id', $room->id)->latest('id')->first();
    expect($summary)->not->toBeNull()
        ->and($summary->body)->toContain('Call ended')
        ->and($summary->body)->toContain('1:15');
});

it('records a declined call and posts the declined summary', function (): void {
    $caller = callActionMember('Caller');
    $callee = callActionMember('Callee');
    $room = callActionRoom($caller);

    $session = CallSession::query()->create([
        'room_id' => $room->id,
        'caller_member_id' => $caller->id,
        'callee_member_id' => $callee->id,
        'status' => 'ringing',
    ]);

    app(EndCall::class)($session, $callee, 'declined');

    expect($session->fresh()->status)->toBe('declined');

    $summary = Message::query()->where('room_id', $room->id)->latest('id')->first();
    expect($summary->body)->toContain('Call declined');
});

it('treats a caller cancelling a ringing call as a missed call', function (): void {
    $caller = callActionMember('Caller');
    $callee = callActionMember('Callee');
    $room = callActionRoom($caller);

    $session = CallSession::query()->create([
        'room_id' => $room->id,
        'caller_member_id' => $caller->id,
        'callee_member_id' => $callee->id,
        'status' => 'ringing',
    ]);

    app(EndCall::class)($session, $caller, 'canceled');

    expect($session->fresh()->status)->toBe('missed');

    $summary = Message::query()->where('room_id', $room->id)->latest('id')->first();
    expect($summary->body)->toContain('**Missed call** from Caller');
});

it('does not re-update an already-closed session or post a second summary', function (): void {
    $caller = callActionMember('Caller');
    $callee = callActionMember('Callee');
    $room = callActionRoom($caller);

    $session = CallSession::query()->create([
        'room_id' => $room->id,
        'caller_member_id' => $caller->id,
        'callee_member_id' => $callee->id,
        'status' => 'ended',
        'ended_at' => now()->subMinute(),
    ]);

    $endedAt = $session->fresh()->ended_at;

    app(EndCall::class)($session, $caller, 'declined');

    $fresh = $session->fresh();
    expect($fresh->status)->toBe('ended')
        ->and($fresh->ended_at->timestamp)->toBe($endedAt->timestamp)
        ->and(Message::query()->where('room_id', $room->id)->count())->toBe(0);
});

it('escapes a malicious caller display name in the missed-call summary (issue #23)', function (): void {
    $caller = callActionMember('a" onmouseover="alert(1)');
    $callee = callActionMember('Callee');
    $room = callActionRoom($caller);

    $session = CallSession::query()->create([
        'room_id' => $room->id,
        'caller_member_id' => $caller->id,
        'callee_member_id' => $callee->id,
        'status' => 'ringing',
    ]);

    app(EndCall::class)($session, $caller, 'canceled');

    $summary = Message::query()->where('room_id', $room->id)->latest('id')->first();

    // The raw quote must be entity-escaped so it cannot break out of any attribute.
    expect($summary->body)->not->toContain('a" onmouseover="alert(1)')
        ->and($summary->body)->toContain('a&quot; onmouseover=&quot;alert(1)')
        ->and($summary->body)->toContain('**Missed call** from');
});

it('escapes angle brackets in a caller display name in the missed-call summary (issue #23)', function (): void {
    $caller = callActionMember('</a><script>alert(1)</script>');
    $callee = callActionMember('Callee');
    $room = callActionRoom($caller);

    $session = CallSession::query()->create([
        'room_id' => $room->id,
        'caller_member_id' => $caller->id,
        'callee_member_id' => $callee->id,
        'status' => 'ringing',
    ]);

    app(EndCall::class)($session, $caller, 'canceled');

    $summary = Message::query()->where('room_id', $room->id)->latest('id')->first();

    expect($summary->body)->not->toContain('<script>')
        ->and($summary->body)->toContain('&lt;script&gt;');
});
