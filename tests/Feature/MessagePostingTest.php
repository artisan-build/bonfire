<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestBot;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Str;
use Livewire\Livewire;

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

it('persists a message when the composer is submitted', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('body', 'Hello, room!')
        ->call('send')
        ->assertSet('body', '');

    expect(Message::query()->count())->toBe(1)
        ->and(Message::query()->first()->body)->toBe('Hello, room!');
});

it('ignores empty messages', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('body', '   ')
        ->call('send');

    expect(Message::query()->count())->toBe(0);
});

it('lets bots post via postAs', function (): void {
    $room = aRoom();
    $bot = TestBot::query()->create(['name' => 'Helpdesk']);
    $botMember = Bonfire::ensureMember($bot, 'Helpdesk Bot');

    $message = Bonfire::postAs($botMember, $room, 'Weekly summary');

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->body)->toBe('Weekly summary')
        ->and($message->member_id)->toBe($botMember->getKey());
});

it('refuses to post to an archived room via the manager', function (): void {
    $room = aRoom(RoomType::Archived->value);
    $user = TestUser::query()->create(['name' => 'Ada']);
    $member = Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    Livewire::test('bonfire::room-show', ['room' => $room])
        ->assertSet('canPost', false);

    expect($room->isArchived())->toBeTrue();
});

it('refuses non-moderator posting in announcement rooms', function (): void {
    $room = aRoom(RoomType::Announcements->value);
    $user = TestUser::query()->create(['name' => 'Ada']);
    Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    Livewire::test('bonfire::room-show', ['room' => $room])
        ->assertSet('canPost', false);
});

it('rejects replying to a reply', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    $member = Bonfire::ensureMember($user, 'Ada');

    $root = Bonfire::postAs($member, $room, 'root');
    $reply = Bonfire::postAs($member, $room, 'first reply', $root);

    expect(fn () => Bonfire::postAs($member, $room, 'nested', $reply))
        ->toThrow(InvalidArgumentException::class);
});
