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

function adminPanelMember(string $name, BonfireRole $role = BonfireRole::Member): Member
{
    $user = TestUser::query()->create(['name' => $name]);

    return Bonfire::ensureMember($user, $name, null, $role);
}

function adminPanelRoom(string $name, int $type, Member $creator): Room
{
    return Room::query()->create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
        'type' => $type,
        'created_by' => $creator->getKey(),
    ]);
}

it('lets an admin add a member to a private channel', function (): void {
    $admin = adminPanelMember('Admin', BonfireRole::Admin);
    $candidate = adminPanelMember('Ada');
    $room = adminPanelRoom('Secret', RoomType::Private->value, $admin);

    auth()->setUser($admin->memberable);

    expect($room->hasMember($candidate))->toBeFalse();

    Livewire::test('bonfire::admin-panel')
        ->call('addMemberToRoom', $room->id, $candidate->id);

    expect($room->fresh()->hasMember($candidate))->toBeTrue();
});

it('lets an admin remove a member from a channel', function (): void {
    $admin = adminPanelMember('Admin', BonfireRole::Admin);
    $member = adminPanelMember('Ada');
    $room = adminPanelRoom('Secret', RoomType::Private->value, $admin);
    $room->addMember($member);

    auth()->setUser($admin->memberable);

    Livewire::test('bonfire::admin-panel')
        ->call('removeMemberFromRoom', $room->id, $member->id);

    expect($room->fresh()->hasMember($member))->toBeFalse();
});

it('does not duplicate when adding an existing channel member', function (): void {
    $admin = adminPanelMember('Admin', BonfireRole::Admin);
    $member = adminPanelMember('Ada');
    $room = adminPanelRoom('General', 0, $admin);
    $room->addMember($member);

    auth()->setUser($admin->memberable);

    Livewire::test('bonfire::admin-panel')
        ->call('addMemberToRoom', $room->id, $member->id);

    expect($room->fresh()->members()->count())->toBe(1);
});

it('forbids non-admins from changing channel membership', function (): void {
    $admin = adminPanelMember('Admin', BonfireRole::Admin);
    $member = adminPanelMember('Ada');
    $room = adminPanelRoom('Secret', RoomType::Private->value, $admin);

    auth()->setUser($member->memberable);

    Livewire::test('bonfire::admin-panel')->assertStatus(403);
});

it('toggles the expanded room state', function (): void {
    $admin = adminPanelMember('Admin', BonfireRole::Admin);
    $room = adminPanelRoom('General', 0, $admin);

    auth()->setUser($admin->memberable);

    Livewire::test('bonfire::admin-panel')
        ->assertSet('expandedRoomId', null)
        ->call('toggleRoomMembers', $room->id)
        ->assertSet('expandedRoomId', $room->id)
        ->call('toggleRoomMembers', $room->id)
        ->assertSet('expandedRoomId', null);
});

it('lists only members not already in the expanded room', function (): void {
    $admin = adminPanelMember('Admin', BonfireRole::Admin);
    $assigned = adminPanelMember('Ada');
    $available = adminPanelMember('Grace');
    $room = adminPanelRoom('Secret', RoomType::Private->value, $admin);
    $room->addMember($assigned);

    auth()->setUser($admin->memberable);

    $component = Livewire::test('bonfire::admin-panel')
        ->call('toggleRoomMembers', $room->id);

    $available_ids = $component->instance()->availableMembers->pluck('id')->all();

    expect($available_ids)
        ->toContain($available->id)
        ->not->toContain($assigned->id);
});

it('filters available members by search term', function (): void {
    $admin = adminPanelMember('Admin', BonfireRole::Admin);
    $ada = adminPanelMember('Ada Lovelace');
    $grace = adminPanelMember('Grace Hopper');
    $room = adminPanelRoom('Secret', RoomType::Private->value, $admin);

    auth()->setUser($admin->memberable);

    $component = Livewire::test('bonfire::admin-panel')
        ->call('toggleRoomMembers', $room->id)
        ->set('memberSearch', 'Grace');

    $matches = $component->instance()->availableMembers->pluck('display_name')->all();

    expect($matches)
        ->toContain('Grace Hopper')
        ->not->toContain('Ada Lovelace');
});
