<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\PollVote;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

function listMember(string $name, BonfireRole $role = BonfireRole::Member): Member
{
    $user = TestUser::query()->create(['name' => $name]);

    return Bonfire::ensureMember($user, $name, null, $role);
}

function listRoom(int $type, Member $creator): Room
{
    return Room::query()->create([
        'name' => 'Room '.Str::random(5),
        'slug' => Str::slug(Str::random(8)),
        'type' => $type,
        'created_by' => $creator->getKey(),
    ]);
}

it('forbids forwarding a message that does not belong to the bound room', function (): void {
    $admin = listMember('Admin', BonfireRole::Admin);
    $member = listMember('Ada');

    $boundRoom = listRoom(0, $admin);
    $otherRoom = listRoom(0, $admin);
    $target = listRoom(0, $admin);

    // The source message lives in $otherRoom, but the component is bound to $boundRoom.
    $foreign = Bonfire::postAs($admin, $otherRoom, 'secret elsewhere');

    auth()->setUser($member->memberable);

    Livewire::test('bonfire::message-list', ['room' => $boundRoom])
        ->call('forwardMessage', $foreign->id, $target->id, '')
        ->assertStatus(403);

    expect(Message::query()->where('room_id', $target->id)->count())->toBe(0);
});

it('forbids forwarding into an archived room', function (): void {
    $admin = listMember('Admin', BonfireRole::Admin);
    $member = listMember('Ada');

    $room = listRoom(0, $admin);
    $archived = listRoom(RoomType::Archived->value, $admin);
    $source = Bonfire::postAs($member, $room, 'hello');

    auth()->setUser($member->memberable);

    Livewire::test('bonfire::message-list', ['room' => $room])
        ->call('forwardMessage', $source->id, $archived->id, '')
        ->assertStatus(403);

    expect(Message::query()->where('room_id', $archived->id)->count())->toBe(0);
});

it('lets a member forward a visible message into a room they belong to', function (): void {
    $admin = listMember('Admin', BonfireRole::Admin);
    $member = listMember('Ada');

    $room = listRoom(0, $admin);
    $target = listRoom(0, $admin);
    $source = Bonfire::postAs($member, $room, 'forward me');

    auth()->setUser($member->memberable);

    Livewire::test('bonfire::message-list', ['room' => $room])
        ->call('forwardMessage', $source->id, $target->id, 'fyi');

    expect(Message::query()->where('room_id', $target->id)->count())->toBe(1);
});

it('forbids voting on a poll message from a different room', function (): void {
    $admin = listMember('Admin', BonfireRole::Admin);
    $member = listMember('Ada');

    $boundRoom = listRoom(0, $admin);
    $otherRoom = listRoom(0, $admin);

    $poll = Message::query()->create([
        'room_id' => $otherRoom->id,
        'member_id' => $admin->id,
        'body' => 'Lunch?',
        'poll' => ['question' => 'Lunch?', 'options' => ['Pizza', 'Tacos']],
    ]);

    auth()->setUser($member->memberable);

    Livewire::test('bonfire::message-list', ['room' => $boundRoom])
        ->call('togglePollVote', $poll->id, 0)
        ->assertStatus(403);

    expect(PollVote::query()->where('message_id', $poll->id)->count())->toBe(0);
});

it('does not react to a message looked up from another room', function (): void {
    $admin = listMember('Admin', BonfireRole::Admin);
    $member = listMember('Ada');

    $boundRoom = listRoom(0, $admin);
    $otherRoom = listRoom(0, $admin);
    $foreign = Bonfire::postAs($admin, $otherRoom, 'elsewhere');

    auth()->setUser($member->memberable);

    Livewire::test('bonfire::message-list', ['room' => $boundRoom])
        ->call('toggleReaction', $foreign->id, '👍');

    expect(DB::table('bonfire_reactions')->where('message_id', $foreign->id)->count())->toBe(0);
});

it('forbids deleting a message that belongs to another room', function (): void {
    $admin = listMember('Admin', BonfireRole::Admin);
    $mod = listMember('Mod', BonfireRole::Moderator);

    $boundRoom = listRoom(0, $admin);
    $otherRoom = listRoom(0, $admin);
    $foreign = Bonfire::postAs($admin, $otherRoom, 'protected');

    auth()->setUser($mod->memberable);

    Livewire::test('bonfire::message-list', ['room' => $boundRoom])
        ->call('deleteMessage', $foreign->id)
        ->assertStatus(403);

    expect(Message::query()->whereKey($foreign->id)->whereNull('deleted_at')->exists())->toBeTrue();
});
