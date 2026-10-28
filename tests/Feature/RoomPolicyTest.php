<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

function policyMember(string $name, BonfireRole $role = BonfireRole::Member, bool $active = true): Member
{
    $user = TestUser::query()->create(['name' => $name]);
    $member = Bonfire::ensureMember($user, $name, null, $role);

    if (! $active) {
        Bonfire::deactivate($member);
        $member->refresh();
    }

    return $member;
}

function policyRoom(int $type, Member $creator): Room
{
    return Room::query()->create([
        'name' => 'Room '.Str::random(5),
        'slug' => Str::slug(Str::random(8)),
        'type' => $type,
        'created_by' => $creator->getKey(),
    ]);
}

// --- view ---------------------------------------------------------------

it('allows any active member to view a public room', function (): void {
    $admin = policyMember('Admin', BonfireRole::Admin);
    $member = policyMember('Ada');
    $room = policyRoom(0, $admin);

    expect(Gate::forUser($member->memberable)->allows('view', $room))->toBeTrue();
});

it('denies a non-member from viewing a private room', function (): void {
    $admin = policyMember('Admin', BonfireRole::Admin);
    $outsider = policyMember('Eve');
    $room = policyRoom(RoomType::Private->value, $admin);

    expect(Gate::forUser($outsider->memberable)->allows('view', $room))->toBeFalse();
});

it('allows a pivot member to view a private room', function (): void {
    $admin = policyMember('Admin', BonfireRole::Admin);
    $member = policyMember('Ada');
    $room = policyRoom(RoomType::Private->value, $admin);
    $room->addMember($member);

    expect(Gate::forUser($member->memberable)->allows('view', $room))->toBeTrue();
});

it('denies an inactive member from viewing any room', function (): void {
    $admin = policyMember('Admin', BonfireRole::Admin);
    $member = policyMember('Ada', BonfireRole::Member, active: false);
    $room = policyRoom(0, $admin);

    expect(Gate::forUser($member->memberable)->allows('view', $room))->toBeFalse();
});

// --- post ---------------------------------------------------------------

it('allows an active member to post in a public room', function (): void {
    $admin = policyMember('Admin', BonfireRole::Admin);
    $member = policyMember('Ada');
    $room = policyRoom(0, $admin);

    expect(Gate::forUser($member->memberable)->allows('post', $room))->toBeTrue();
});

it('forbids posting in archived rooms', function (): void {
    $admin = policyMember('Admin', BonfireRole::Admin);
    $member = policyMember('Ada');
    $room = policyRoom(RoomType::Archived->value, $admin);

    expect(Gate::forUser($member->memberable)->allows('post', $room))->toBeFalse();
});

it('only lets moderators and admins post in announcement rooms', function (): void {
    $admin = policyMember('Admin', BonfireRole::Admin);
    $member = policyMember('Ada');
    $mod = policyMember('Mod', BonfireRole::Moderator);
    $room = policyRoom(RoomType::Announcements->value, $admin);

    expect(Gate::forUser($member->memberable)->allows('post', $room))->toBeFalse()
        ->and(Gate::forUser($mod->memberable)->allows('post', $room))->toBeTrue()
        ->and(Gate::forUser($admin->memberable)->allows('post', $room))->toBeTrue();
});

it('forbids posting in a private room you do not belong to', function (): void {
    $admin = policyMember('Admin', BonfireRole::Admin);
    $outsider = policyMember('Eve');
    $room = policyRoom(RoomType::Private->value, $admin);

    expect(Gate::forUser($outsider->memberable)->allows('post', $room))->toBeFalse();
});

// --- manage -------------------------------------------------------------

it('reserves room management for admins', function (): void {
    $admin = policyMember('Admin', BonfireRole::Admin);
    $mod = policyMember('Mod', BonfireRole::Moderator);
    $member = policyMember('Ada');

    expect(Gate::forUser($admin->memberable)->allows('manage', Room::class))->toBeTrue()
        ->and(Gate::forUser($mod->memberable)->allows('manage', Room::class))->toBeFalse()
        ->and(Gate::forUser($member->memberable)->allows('manage', Room::class))->toBeFalse();
});

// --- create -------------------------------------------------------------

it('lets any active member create a room', function (): void {
    $member = policyMember('Ada');
    $inactive = policyMember('Gone', BonfireRole::Member, active: false);

    expect(Gate::forUser($member->memberable)->allows('create', Room::class))->toBeTrue()
        ->and(Gate::forUser($inactive->memberable)->allows('create', Room::class))->toBeFalse();
});

// --- forwardInto --------------------------------------------------------

it('forbids forwarding into an archived room', function (): void {
    $admin = policyMember('Admin', BonfireRole::Admin);
    $member = policyMember('Ada');
    $room = policyRoom(RoomType::Archived->value, $admin);

    expect(Gate::forUser($member->memberable)->allows('forwardInto', $room))->toBeFalse();
});

it('forbids forwarding into a private room you are not in', function (): void {
    $admin = policyMember('Admin', BonfireRole::Admin);
    $outsider = policyMember('Eve');
    $room = policyRoom(RoomType::Private->value, $admin);

    expect(Gate::forUser($outsider->memberable)->allows('forwardInto', $room))->toBeFalse();
});

// --- useRealtime --------------------------------------------------------

it('gates realtime entry points on room access', function (): void {
    $admin = policyMember('Admin', BonfireRole::Admin);
    $member = policyMember('Ada');
    $outsider = policyMember('Eve');
    $room = policyRoom(RoomType::Private->value, $admin);
    $room->addMember($member);

    expect(Gate::forUser($member->memberable)->allows('useRealtime', $room))->toBeTrue()
        ->and(Gate::forUser($outsider->memberable)->allows('useRealtime', $room))->toBeFalse();
});
