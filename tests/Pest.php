<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use ArtisanBuild\Bonfire\Tests\TestCase;
use Illuminate\Support\Str;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Shared room factory used across the package's domain tests.
 */
function aRoom(int $type = 0): Room
{
    $adminUser = TestUser::query()->create(['name' => 'Admin']);
    $admin = Bonfire::ensureMember($adminUser, 'Admin', null, BonfireRole::Admin);

    return Room::query()->create([
        'name' => 'Room '.Str::random(6),
        'slug' => Str::random(8),
        'type' => $type,
        'created_by' => $admin->getKey(),
    ]);
}

/**
 * Create a host user paired with its Bonfire member for the API suite.
 *
 * @return array{0: TestUser, 1: Member}
 */
function apiActor(string $name, BonfireRole $role = BonfireRole::Member): array
{
    $user = TestUser::query()->create(['name' => $name]);
    $member = Bonfire::ensureMember($user, $name, null, $role);

    return [$user, $member];
}

/**
 * Create a room owned by a fresh admin, returning the room and its admin member.
 *
 * @return array{0: Room, 1: Member}
 */
function apiRoom(int $type = 0): array
{
    [, $admin] = apiActor('Admin '.Str::random(4), BonfireRole::Admin);

    $room = Room::query()->create([
        'name' => 'Room '.Str::random(6),
        'slug' => Str::random(10),
        'type' => $type,
        'created_by' => $admin->getKey(),
    ]);

    return [$room, $admin];
}
