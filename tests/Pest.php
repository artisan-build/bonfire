<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Facades\Bonfire;
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
