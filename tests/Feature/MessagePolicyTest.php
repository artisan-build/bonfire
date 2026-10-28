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

function msgPolicyMember(string $name, BonfireRole $role = BonfireRole::Member): Member
{
    $user = TestUser::query()->create(['name' => $name]);

    return Bonfire::ensureMember($user, $name, null, $role);
}

function msgPolicyRoom(int $type, Member $creator): Room
{
    return Room::query()->create([
        'name' => 'Room '.Str::random(5),
        'slug' => Str::slug(Str::random(8)),
        'type' => $type,
        'created_by' => $creator->getKey(),
    ]);
}

it('lets the author delete their own message', function (): void {
    $admin = msgPolicyMember('Admin', BonfireRole::Admin);
    $author = msgPolicyMember('Ada');
    $room = msgPolicyRoom(0, $admin);
    $message = Bonfire::postAs($author, $room, 'mine');

    expect(Gate::forUser($author->memberable)->allows('delete', $message))->toBeTrue();
});

it('forbids a plain member from deleting someone elses message', function (): void {
    $admin = msgPolicyMember('Admin', BonfireRole::Admin);
    $author = msgPolicyMember('Grace');
    $other = msgPolicyMember('Ada');
    $room = msgPolicyRoom(0, $admin);
    $message = Bonfire::postAs($author, $room, 'theirs');

    expect(Gate::forUser($other->memberable)->allows('delete', $message))->toBeFalse();
});

it('lets moderators and admins delete any message', function (): void {
    $admin = msgPolicyMember('Admin', BonfireRole::Admin);
    $author = msgPolicyMember('Grace');
    $mod = msgPolicyMember('Mod', BonfireRole::Moderator);
    $room = msgPolicyRoom(0, $admin);
    $message = Bonfire::postAs($author, $room, 'spam');

    expect(Gate::forUser($mod->memberable)->allows('delete', $message))->toBeTrue()
        ->and(Gate::forUser($admin->memberable)->allows('delete', $message))->toBeTrue();
});

it('only lets the author edit a message', function (): void {
    $admin = msgPolicyMember('Admin', BonfireRole::Admin);
    $author = msgPolicyMember('Ada');
    $mod = msgPolicyMember('Mod', BonfireRole::Moderator);
    $room = msgPolicyRoom(0, $admin);
    $message = Bonfire::postAs($author, $room, 'mine');

    expect(Gate::forUser($author->memberable)->allows('update', $message))->toBeTrue()
        ->and(Gate::forUser($mod->memberable)->allows('update', $message))->toBeFalse();
});

it('gates react and vote on access to the message room', function (): void {
    $admin = msgPolicyMember('Admin', BonfireRole::Admin);
    $member = msgPolicyMember('Ada');
    $outsider = msgPolicyMember('Eve');
    $room = msgPolicyRoom(RoomType::Private->value, $admin);
    $room->addMember($member);
    $message = Bonfire::postAs($admin, $room, 'private');

    expect(Gate::forUser($member->memberable)->allows('react', $message))->toBeTrue()
        ->and(Gate::forUser($member->memberable)->allows('vote', $message))->toBeTrue()
        ->and(Gate::forUser($outsider->memberable)->allows('react', $message))->toBeFalse()
        ->and(Gate::forUser($outsider->memberable)->allows('vote', $message))->toBeFalse();
});

it('forbids pinning a trashed message', function (): void {
    $admin = msgPolicyMember('Admin', BonfireRole::Admin);
    $room = msgPolicyRoom(0, $admin);
    $message = Bonfire::postAs($admin, $room, 'gone');
    $message->delete();

    expect(Gate::forUser($admin->memberable)->allows('pin', $message))->toBeFalse();
});
