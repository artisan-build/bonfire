<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Str;
use Livewire\Livewire;

function meetingMember(string $name, BonfireRole $role = BonfireRole::Member): Member
{
    $user = TestUser::query()->create(['name' => $name]);

    return Bonfire::ensureMember($user, $name, null, $role);
}

function meetingRoom(int $type, Member $creator): Room
{
    return Room::query()->create([
        'name' => 'Room '.Str::random(5),
        'slug' => Str::slug(Str::random(8)),
        'type' => $type,
        'created_by' => $creator->getKey(),
    ]);
}

it('forbids a non-member from announcing a meeting start in a private room', function (): void {
    $admin = meetingMember('Admin', BonfireRole::Admin);
    $outsider = meetingMember('Eve');
    $room = meetingRoom(RoomType::Private->value, $admin);

    auth()->setUser($outsider->memberable);

    Livewire::test('bonfire::meeting-panel')
        ->call('announceStart', $room->id)
        ->assertStatus(403);

    expect(Message::query()->where('room_id', $room->id)->count())->toBe(0);
});

it('forbids a non-member from announcing a meeting end in a private room', function (): void {
    $admin = meetingMember('Admin', BonfireRole::Admin);
    $outsider = meetingMember('Eve');
    $room = meetingRoom(RoomType::Private->value, $admin);

    auth()->setUser($outsider->memberable);

    Livewire::test('bonfire::meeting-panel')
        ->call('announceEnd', $room->id, 3)
        ->assertStatus(403);
});

it('forbids a non-member from inviting to a meeting in a private room', function (): void {
    $admin = meetingMember('Admin', BonfireRole::Admin);
    $outsider = meetingMember('Eve');
    $target = meetingMember('Ada');
    $room = meetingRoom(RoomType::Private->value, $admin);

    auth()->setUser($outsider->memberable);

    Livewire::test('bonfire::meeting-panel')
        ->call('invite', $room->id, $target->id)
        ->assertStatus(403);

    expect(Message::query()->where('room_id', $room->id)->count())->toBe(0);
});

it('lets a private-room member announce a meeting', function (): void {
    $admin = meetingMember('Admin', BonfireRole::Admin);
    $member = meetingMember('Ada');
    $room = meetingRoom(RoomType::Private->value, $admin);
    $room->addMember($member);

    auth()->setUser($member->memberable);

    Livewire::test('bonfire::meeting-panel')
        ->call('announceStart', $room->id);

    expect(Message::query()->where('room_id', $room->id)->count())->toBe(1);
});

it('lets any active member announce a meeting in a public room', function (): void {
    $admin = meetingMember('Admin', BonfireRole::Admin);
    $member = meetingMember('Ada');
    $room = meetingRoom(0, $admin);

    auth()->setUser($member->memberable);

    Livewire::test('bonfire::meeting-panel')
        ->call('announceStart', $room->id);

    expect(Message::query()->where('room_id', $room->id)->count())->toBe(1);
});
