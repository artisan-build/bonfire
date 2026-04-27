<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Str;
use Livewire\Livewire;

function room(string $name, int $type, int $creatorId): Room
{
    return Room::query()->create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::random(4),
        'type' => $type,
        'created_by' => $creatorId,
    ]);
}

it('lists public rooms and hides private rooms not belonging to the member', function (): void {
    $adminUser = TestUser::query()->create(['name' => 'Admin']);
    $admin = Bonfire::ensureMember($adminUser, 'Admin', null, BonfireRole::Admin);

    $user = TestUser::query()->create(['name' => 'Ada']);
    $member = Bonfire::ensureMember($user, 'Ada');

    $public = room('Public', 0, $admin->getKey());
    $secret = room('Secret', RoomType::Private->value, $admin->getKey());
    $theirs = room('Ours', RoomType::Private->value, $admin->getKey());
    $theirs->addMember($member);

    auth()->setUser($user);

    Livewire::test('bonfire::rooms')
        ->assertSee($public->name)
        ->assertSee($theirs->name)
        ->assertDontSee($secret->name);
});

it('shows public rooms to guests without a member record', function (): void {
    $adminUser = TestUser::query()->create(['name' => 'Admin']);
    $admin = Bonfire::ensureMember($adminUser, 'Admin', null, BonfireRole::Admin);

    $public = room('Public', 0, $admin->getKey());
    $secret = room('Secret', RoomType::Private->value, $admin->getKey());

    Livewire::test('bonfire::rooms')
        ->assertSee($public->name)
        ->assertDontSee($secret->name);
});
