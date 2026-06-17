<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\CallSession;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Str;
use Livewire\Livewire;

function callMember(string $name, BonfireRole $role = BonfireRole::Member): Member
{
    $user = TestUser::query()->create(['name' => $name]);

    return Bonfire::ensureMember($user, $name, null, $role);
}

function callRoom(int $type, Member $creator): Room
{
    return Room::query()->create([
        'name' => 'Room '.Str::random(5),
        'slug' => Str::slug(Str::random(8)),
        'type' => $type,
        'created_by' => $creator->getKey(),
    ]);
}

it('forbids a non-member from relaying a call signal in a private room', function (): void {
    $admin = callMember('Admin', BonfireRole::Admin);
    $caller = callMember('Caller');
    $callee = callMember('Callee');
    $outsider = callMember('Eve');

    $room = callRoom(RoomType::Private->value, $admin);
    $room->addMember($caller);
    $room->addMember($callee);

    $session = CallSession::query()->create([
        'room_id' => $room->id,
        'caller_member_id' => $caller->id,
        'callee_member_id' => $callee->id,
        'status' => 'ringing',
    ]);

    auth()->setUser($outsider->memberable);

    Livewire::test('bonfire::call-panel')
        ->call('relaySignal', $session->id, 'offer', ['sdp' => 'x'])
        ->assertStatus(403);
});

it('forbids a participant who has lost private-room access from ending the call', function (): void {
    // A caller who participates in the session but is no longer a member of the
    // private room must not be able to mutate it — the room-membership guard
    // fires after the participant check.
    $admin = callMember('Admin', BonfireRole::Admin);
    $caller = callMember('Caller');
    $callee = callMember('Callee');

    $room = callRoom(RoomType::Private->value, $admin);
    $room->addMember($callee);
    // Note: $caller is intentionally NOT a room member.

    $session = CallSession::query()->create([
        'room_id' => $room->id,
        'caller_member_id' => $caller->id,
        'callee_member_id' => $callee->id,
        'status' => 'active',
    ]);

    auth()->setUser($caller->memberable);

    Livewire::test('bonfire::call-panel')
        ->call('endCall', $session->id, 'ended')
        ->assertStatus(403);

    expect($session->fresh()->status)->toBe('active');
});
