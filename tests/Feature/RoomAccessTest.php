<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Str;
use Livewire\Livewire;

function makeMember(string $name, BonfireRole $role = BonfireRole::Member): Member
{
    $user = TestUser::query()->create(['name' => $name]);

    return Bonfire::ensureMember($user, $name, null, $role);
}

function makeRoom(string $name, int $type, Member $creator): Room
{
    return Room::query()->create([
        'name' => $name,
        'slug' => Str::slug($name),
        'type' => $type,
        'created_by' => $creator->getKey(),
    ]);
}

it('allows any active member to view a public room', function (): void {
    $admin = makeMember('Admin', BonfireRole::Admin);
    $member = makeMember('Ada');
    $room = makeRoom('General', 0, $admin);

    auth()->setUser($member->memberable);

    Livewire::test('bonfire::room-show', ['room' => $room])
        ->assertOk()
        ->assertSet('room.id', $room->id);
});

it('denies a non-pivot member from a private room', function (): void {
    $admin = makeMember('Admin', BonfireRole::Admin);
    $member = makeMember('Ada');
    $room = makeRoom('Secret', RoomType::Private->value, $admin);

    auth()->setUser($member->memberable);

    Livewire::test('bonfire::room-show', ['room' => $room])
        ->assertStatus(403);
});

it('allows pivot members into a private room', function (): void {
    $admin = makeMember('Admin', BonfireRole::Admin);
    $member = makeMember('Ada');
    $room = makeRoom('Secret', RoomType::Private->value, $admin);
    $room->addMember($member);

    auth()->setUser($member->memberable);

    Livewire::test('bonfire::room-show', ['room' => $room])
        ->assertOk();
});

it('hides the composer in archived rooms', function (): void {
    $admin = makeMember('Admin', BonfireRole::Admin);
    $member = makeMember('Ada');
    $room = makeRoom('Old', RoomType::Archived->value, $admin);

    auth()->setUser($member->memberable);

    Livewire::test('bonfire::room-show', ['room' => $room])
        ->assertSet('room.id', $room->id)
        ->assertSet('canPost', false);
});

it('only lets moderators post in announcement rooms', function (): void {
    $admin = makeMember('Admin', BonfireRole::Admin);
    $member = makeMember('Ada');
    $moderator = makeMember('Mod', BonfireRole::Moderator);
    $room = makeRoom('News', RoomType::Announcements->value, $admin);

    auth()->setUser($member->memberable);
    Livewire::test('bonfire::room-show', ['room' => $room])
        ->assertSet('canPost', false);

    auth()->setUser($moderator->memberable);
    Livewire::test('bonfire::room-show', ['room' => $room])
        ->assertSet('canPost', true);
});

it('denies an inactive member the composer', function (): void {
    $admin = makeMember('Admin', BonfireRole::Admin);
    $member = makeMember('Ada');
    Bonfire::deactivate($member);

    $room = makeRoom('General', 0, $admin);

    auth()->setUser($member->memberable);

    Livewire::test('bonfire::room-show', ['room' => $room])
        ->assertSet('canPost', false);
});
