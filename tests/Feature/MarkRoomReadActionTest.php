<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Actions\MarkRoomRead;
use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\UnreadTracker;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function actionMember(string $name, BonfireRole $role = BonfireRole::Member): Member
{
    $user = TestUser::query()->create(['name' => $name]);

    return Bonfire::ensureMember($user, $name, null, $role);
}

function actionRoom(string $name, int $type, Member $creator): Room
{
    return Room::query()->create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::random(4),
        'type' => $type,
        'created_by' => $creator->getKey(),
    ]);
}

it('marks a room as read through the container-resolved action', function (): void {
    $admin = actionMember('Admin', BonfireRole::Admin);
    $member = actionMember('Ada');
    $room = actionRoom('General', 0, $admin);

    $markRoomRead = app(MarkRoomRead::class);
    $markRoomRead($room, $member);

    $tracker = new UnreadTracker;

    expect($tracker->lastReadAt($room, $member))->not->toBeNull()
        ->and(DB::table('bonfire_room_reads')
            ->where('member_id', $member->getKey())
            ->where('room_id', $room->getKey())
            ->count())->toBe(1);
});
