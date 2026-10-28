<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Actions\AddMemberToRoom;
use ArtisanBuild\Bonfire\Actions\CreateRoom;
use ArtisanBuild\Bonfire\Actions\DeleteChannel;
use ArtisanBuild\Bonfire\Actions\ForceDeleteRoom;
use ArtisanBuild\Bonfire\Actions\KickMember;
use ArtisanBuild\Bonfire\Actions\RemoveMemberFromRoom;
use ArtisanBuild\Bonfire\Actions\RestoreChannel;
use ArtisanBuild\Bonfire\Actions\ToggleMemberActive;
use ArtisanBuild\Bonfire\Actions\UpdateRoom;
use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function apaMember(string $name, BonfireRole $role = BonfireRole::Member): Member
{
    return Bonfire::ensureMember(TestUser::query()->create(['name' => $name]), $name, null, $role);
}

function apaRoom(int $type = 0, ?string $slug = null): Room
{
    $admin = apaMember('Admin '.Str::random(4), BonfireRole::Admin);

    return Room::query()->create([
        'name' => 'Room '.Str::random(6),
        'slug' => $slug ?? Str::random(10),
        'type' => $type,
        'created_by' => $admin->getKey(),
    ]);
}

// --- AddMemberToRoom / RemoveMemberFromRoom ---

it('adds a member to a room recording the actor as the adder', function (): void {
    $actor = apaMember('Actor');
    $member = apaMember('Ada');
    $room = apaRoom();

    app(AddMemberToRoom::class)($room, $member, $actor);

    expect($room->fresh()->hasMember($member))->toBeTrue()
        ->and(DB::table('bonfire_member_room')
            ->where('room_id', $room->id)
            ->where('member_id', $member->id)
            ->value('created_by'))->toBe($actor->id);
});

it('removes a member from a room', function (): void {
    $member = apaMember('Ada');
    $room = apaRoom();
    $room->addMember($member);

    app(RemoveMemberFromRoom::class)($room, $member);

    expect($room->fresh()->hasMember($member))->toBeFalse();
});

// --- CreateRoom ---

it('creates a room with the combined type bitmask, random-suffix slug, and creator', function (): void {
    $creator = apaMember('Creator');

    $room = app(CreateRoom::class)(
        $creator,
        'Project X',
        '  a description  ',
        true,
        true,
        true,
    );

    expect($room->type)->toBe(RoomType::Private->value | RoomType::Archived->value | RoomType::Announcements->value)
        ->and($room->type)->toBe(7)
        ->and($room->description)->toBe('a description')
        ->and($room->created_by)->toBe($creator->getKey())
        ->and($room->slug)->toStartWith('project-x-')
        ->and($room->slug)->toMatch('/^project-x-[a-z0-9]{6}$/');
});

it('gives each created room a unique random-suffix slug for the same name', function (): void {
    $creator = apaMember('Creator');

    $first = app(CreateRoom::class)($creator, 'General', null, false, false, false);
    $second = app(CreateRoom::class)($creator, 'General', null, false, false, false);

    expect($first->slug)->not->toBe($second->slug)
        ->and($first->type)->toBe(0)
        ->and($first->description)->toBeNull();
});

// --- UpdateRoom ---

it('sets name and description, trimming empty values to null', function (): void {
    $room = apaRoom();

    expect(app(UpdateRoom::class)($room, 'name', '  Renamed  '))->toBeTrue();
    expect(app(UpdateRoom::class)($room, 'description', '   '))->toBeTrue();

    $room->refresh();
    expect($room->name)->toBe('Renamed')
        ->and($room->description)->toBeNull();
});

it('toggles each room-type flag bit on and off', function (): void {
    $cases = [
        'private' => RoomType::Private,
        'archived' => RoomType::Archived,
        'announcements' => RoomType::Announcements,
    ];

    foreach ($cases as $field => $flag) {
        $room = apaRoom();

        app(UpdateRoom::class)($room, $field, true);
        expect(RoomType::has($room->fresh()->type, $flag))->toBeTrue();

        app(UpdateRoom::class)($room, $field, false);
        expect(RoomType::has($room->fresh()->type, $flag))->toBeFalse();
    }
});

it('treats an unknown update field as a no-op', function (): void {
    $room = apaRoom(RoomType::Private->value);

    expect(app(UpdateRoom::class)($room, 'bogus', true))->toBeFalse()
        ->and($room->fresh()->type)->toBe(RoomType::Private->value);
});

// --- DeleteChannel / RestoreChannel / ForceDeleteRoom ---

it('soft-deletes, restores, then hard-deletes a room', function (): void {
    $room = apaRoom();

    app(DeleteChannel::class)($room);
    expect(Room::query()->whereKey($room->id)->exists())->toBeFalse()
        ->and(Room::onlyTrashed()->whereKey($room->id)->exists())->toBeTrue();

    app(RestoreChannel::class)($room);
    expect($room->fresh()->trashed())->toBeFalse();

    app(DeleteChannel::class)($room);
    app(ForceDeleteRoom::class)(Room::onlyTrashed()->findOrFail($room->id));
    expect(Room::onlyTrashed()->whereKey($room->id)->exists())->toBeFalse()
        ->and(Room::withTrashed()->whereKey($room->id)->exists())->toBeFalse();
});

// --- KickMember ---

it('detaches all of a members room pivots and deletes the member', function (): void {
    $member = apaMember('Ada');
    $roomA = apaRoom();
    $roomB = apaRoom();
    $roomA->addMember($member);
    $roomB->addMember($member);

    app(KickMember::class)($member);

    expect(Member::query()->whereKey($member->id)->exists())->toBeFalse()
        ->and(DB::table('bonfire_member_room')->where('member_id', $member->id)->count())->toBe(0);
});

// --- ToggleMemberActive ---

it('flips a members active state both directions', function (): void {
    $member = apaMember('Ada');

    expect($member->is_active)->toBeTrue();

    app(ToggleMemberActive::class)($member);
    expect($member->fresh()->is_active)->toBeFalse();

    app(ToggleMemberActive::class)($member->fresh());
    expect($member->fresh()->is_active)->toBeTrue();
});
