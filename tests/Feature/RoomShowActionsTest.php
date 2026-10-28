<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Actions\ToggleRoomStar;
use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function rsaMember(string $name): Member
{
    return Bonfire::ensureMember(TestUser::query()->create(['name' => $name]), $name);
}

function rsaRoom(int $type = 0): Room
{
    $admin = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Admin']), 'Admin', null, BonfireRole::Admin);

    return Room::query()->create([
        'name' => 'Room '.Str::random(6),
        'slug' => Str::random(8),
        'type' => $type,
        'created_by' => $admin->getKey(),
    ]);
}

it('stars and unstars a room for a member', function (): void {
    $member = rsaMember('Ada');
    $room = rsaRoom();

    $starred = fn (): bool => DB::table('bonfire_starred_rooms')
        ->where('member_id', $member->id)
        ->where('room_id', $room->id)
        ->exists();

    $nowStarred = app(ToggleRoomStar::class)($member, $room);
    expect($nowStarred)->toBeTrue()
        ->and($starred())->toBeTrue();

    $nowStarred = app(ToggleRoomStar::class)($member, $room);
    expect($nowStarred)->toBeFalse()
        ->and($starred())->toBeFalse();
});
