<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Str;
use Livewire\Livewire;

function adminAuthzMember(string $name, BonfireRole $role = BonfireRole::Member): Member
{
    $user = TestUser::query()->create(['name' => $name]);

    return Bonfire::ensureMember($user, $name, null, $role);
}

function adminAuthzRoom(Member $creator): Room
{
    return Room::query()->create([
        'name' => 'Room '.Str::random(5),
        'slug' => Str::slug(Str::random(8)),
        'type' => 0,
        'created_by' => $creator->getKey(),
    ]);
}

// The admin panel gates its whole surface at mount, so a non-admin can never
// reach the privileged actions (deleteRoom/updateRoom/changeRole/toggleActive).
// The per-method authorize() guards are defense-in-depth, verified by the Room
// and Member policy tests; here we assert the panel is unreachable for them.
it('forbids a non-admin from mounting the admin panel at all', function (): void {
    adminAuthzMember('Admin', BonfireRole::Admin);
    $member = adminAuthzMember('Ada');

    auth()->setUser($member->memberable);

    Livewire::test('bonfire::admin-panel')->assertStatus(403);
});

it('forbids a moderator from mounting the admin panel', function (): void {
    adminAuthzMember('Admin', BonfireRole::Admin);
    $mod = adminAuthzMember('Mod', BonfireRole::Moderator);

    auth()->setUser($mod->memberable);

    Livewire::test('bonfire::admin-panel')->assertStatus(403);
});

it('lets an admin delete and update rooms and change roles', function (): void {
    $admin = adminAuthzMember('Admin', BonfireRole::Admin);
    $target = adminAuthzMember('Bob');
    $room = adminAuthzRoom($admin);

    auth()->setUser($admin->memberable);

    Livewire::test('bonfire::admin-panel')
        ->call('updateRoom', $room->id, 'name', 'Renamed')
        ->call('changeRole', $target->id, BonfireRole::Moderator->value)
        ->call('deleteRoom', $room->id);

    expect($room->fresh()->name)->toBe('Renamed')
        ->and($target->fresh()->role)->toBe(BonfireRole::Moderator)
        ->and(Room::query()->whereKey($room->id)->exists())->toBeFalse();
});

it('forbids an admin from kicking themselves', function (): void {
    $admin = adminAuthzMember('Admin', BonfireRole::Admin);

    auth()->setUser($admin->memberable);

    Livewire::test('bonfire::admin-panel')
        ->call('kickMember', $admin->id)
        ->assertStatus(403);

    expect(Member::query()->whereKey($admin->id)->exists())->toBeTrue();
});
