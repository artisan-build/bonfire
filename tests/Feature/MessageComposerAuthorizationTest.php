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

function composerMember(string $name, BonfireRole $role = BonfireRole::Member): Member
{
    $user = TestUser::query()->create(['name' => $name]);

    return Bonfire::ensureMember($user, $name, null, $role);
}

function composerRoom(int $type, Member $creator): Room
{
    return Room::query()->create([
        'name' => 'Room '.Str::random(5),
        'slug' => Str::slug(Str::random(8)),
        'type' => $type,
        'created_by' => $creator->getKey(),
    ]);
}

it('forbids replying to a parent that lives in a different room', function (): void {
    $admin = composerMember('Admin', BonfireRole::Admin);
    $member = composerMember('Ada');

    $roomA = composerRoom(0, $admin);
    $roomB = composerRoom(0, $admin);

    // Parent lives in room B; composer is bound to room A.
    $foreignParent = Bonfire::postAs($admin, $roomB, 'over here');

    auth()->setUser($member->memberable);

    Livewire::test('bonfire::message-composer', ['room' => $roomA, 'parentId' => $foreignParent->id])
        ->set('body', 'sneaky reply')
        ->call('send')
        ->assertStatus(403);

    expect(Message::query()->where('parent_id', $foreignParent->id)->count())->toBe(0);
});

it('forbids a non-member from posting in a private room', function (): void {
    $admin = composerMember('Admin', BonfireRole::Admin);
    $outsider = composerMember('Eve');
    $room = composerRoom(RoomType::Private->value, $admin);

    auth()->setUser($outsider->memberable);

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('body', 'let me in')
        ->call('send')
        ->assertStatus(403);

    expect(Message::query()->where('room_id', $room->id)->count())->toBe(0);
});

it('forbids a plain member from posting in an announcement room', function (): void {
    $admin = composerMember('Admin', BonfireRole::Admin);
    $member = composerMember('Ada');
    $room = composerRoom(RoomType::Announcements->value, $admin);

    auth()->setUser($member->memberable);

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('body', 'announcement')
        ->call('send')
        ->assertStatus(403);

    expect(Message::query()->where('room_id', $room->id)->count())->toBe(0);
});

it('lets a moderator post in an announcement room', function (): void {
    $admin = composerMember('Admin', BonfireRole::Admin);
    $mod = composerMember('Mod', BonfireRole::Moderator);
    $room = composerRoom(RoomType::Announcements->value, $admin);

    auth()->setUser($mod->memberable);

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('body', 'official notice')
        ->call('send');

    expect(Message::query()->where('room_id', $room->id)->count())->toBe(1);
});

it('lets a member reply to a parent in the same room', function (): void {
    $admin = composerMember('Admin', BonfireRole::Admin);
    $member = composerMember('Ada');
    $room = composerRoom(0, $admin);
    $parent = Bonfire::postAs($admin, $room, 'thread root');

    auth()->setUser($member->memberable);

    Livewire::test('bonfire::message-composer', ['room' => $room, 'parentId' => $parent->id])
        ->set('body', 'a reply')
        ->call('send');

    expect(Message::query()->where('parent_id', $parent->id)->count())->toBe(1);
});
