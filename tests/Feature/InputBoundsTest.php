<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\ChannelSection;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    app('router')->get('bonfire/{room:slug}', fn () => '')->name('bonfire.room.show');
    app('router')->get('/profile', fn () => '')->name('profile.edit');
    app('router')->post('/logout', fn () => '')->name('logout');
});

function ibMember(string $name, BonfireRole $role = BonfireRole::Member): Member
{
    return Bonfire::ensureMember(TestUser::query()->create(['name' => $name]), $name, null, $role);
}

function ibRoom(): Room
{
    $admin = ibMember('Admin '.Str::random(4), BonfireRole::Admin);

    return Room::query()->create([
        'name' => 'Room '.Str::random(6),
        'slug' => Str::random(10),
        'type' => 0,
        'created_by' => $admin->getKey(),
    ]);
}

// --- AC1: message body bound ---

it('rejects a message body that exceeds the max length', function (): void {
    $room = ibRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('body', str_repeat('a', 10001))
        ->call('send')
        ->assertHasErrors('body');

    expect(Message::query()->count())->toBe(0);
});

it('still sends a normal-length message body', function (): void {
    $room = ibRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('body', 'Hello, room!')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('body', '');

    expect(Message::query()->count())->toBe(1);
});

// --- AC2: room creation bounds ---

it('rejects creating a room with an over-cap name and persists nothing', function (): void {
    $admin = ibMember('Admin', BonfireRole::Admin);
    auth()->setUser($admin->memberable);

    $before = Room::query()->count();

    Livewire::test('bonfire::admin-panel')
        ->set('newName', str_repeat('a', 101))
        ->call('createRoom')
        ->assertHasErrors('newName');

    expect(Room::query()->count())->toBe($before);
});

it('rejects creating a room with an over-cap description and persists nothing', function (): void {
    $admin = ibMember('Admin', BonfireRole::Admin);
    auth()->setUser($admin->memberable);

    $before = Room::query()->count();

    Livewire::test('bonfire::admin-panel')
        ->set('newName', 'Valid Name')
        ->set('newDescription', str_repeat('a', 2001))
        ->call('createRoom')
        ->assertHasErrors('newDescription');

    expect(Room::query()->count())->toBe($before);
});

it('still creates a room with valid name and description', function (): void {
    $admin = ibMember('Admin', BonfireRole::Admin);
    auth()->setUser($admin->memberable);

    $before = Room::query()->count();

    Livewire::test('bonfire::admin-panel')
        ->set('newName', 'General')
        ->set('newDescription', 'A place to chat')
        ->call('createRoom')
        ->assertHasNoErrors();

    expect(Room::query()->count())->toBe($before + 1);
});

// --- AC3: channel creation bounds ---

it('rejects creating a channel with an over-cap name and persists nothing', function (): void {
    $admin = ibMember('Admin', BonfireRole::Admin);
    auth()->setUser($admin->memberable);

    $before = Room::query()->count();

    Livewire::test('bonfire::rooms')
        ->set('newChannelName', str_repeat('a', 101))
        ->call('createChannel')
        ->assertHasErrors('newChannelName');

    expect(Room::query()->count())->toBe($before);
});

it('rejects creating a channel with an over-cap description and persists nothing', function (): void {
    $admin = ibMember('Admin', BonfireRole::Admin);
    auth()->setUser($admin->memberable);

    $before = Room::query()->count();

    Livewire::test('bonfire::rooms')
        ->set('newChannelName', 'valid-channel')
        ->set('newChannelDescription', str_repeat('a', 2001))
        ->call('createChannel')
        ->assertHasErrors('newChannelDescription');

    expect(Room::query()->count())->toBe($before);
});

it('still creates a channel with a valid name', function (): void {
    $admin = ibMember('Admin', BonfireRole::Admin);
    auth()->setUser($admin->memberable);

    $before = Room::query()->count();

    Livewire::test('bonfire::rooms')
        ->set('newChannelName', 'general')
        ->set('newChannelDescription', 'A valid channel')
        ->call('createChannel')
        ->assertHasNoErrors();

    expect(Room::query()->count())->toBe($before + 1);
});

// --- AC4: section name bound ---

it('rejects creating a channel section with an over-cap name', function (): void {
    $user = TestUser::query()->create(['name' => 'Ada']);
    Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    Livewire::test('bonfire::rooms')
        ->call('createSection', str_repeat('a', 101))
        ->assertHasErrors('name');

    expect(ChannelSection::query()->count())->toBe(0);
});

it('rejects renaming a channel section to an over-cap name', function (): void {
    $user = TestUser::query()->create(['name' => 'Ada']);
    $member = Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    $section = ChannelSection::query()->create([
        'member_id' => $member->getKey(),
        'name' => 'Original',
        'position' => 1,
    ]);

    Livewire::test('bonfire::rooms')
        ->call('renameSection', $section->getKey(), str_repeat('a', 101))
        ->assertHasErrors('name');

    expect($section->fresh()->name)->toBe('Original');
});

// --- AC6: editing an existing message is bounded ---

it('rejects editing a message to an over-cap body and leaves it unchanged', function (): void {
    $admin = ibMember('Admin', BonfireRole::Admin);
    $room = Room::query()->create([
        'name' => 'Room '.Str::random(6),
        'slug' => Str::random(10),
        'type' => 0,
        'created_by' => $admin->getKey(),
    ]);

    $author = ibMember('Ada');
    $room->addMember($author);
    auth()->setUser($author->memberable);

    $message = Bonfire::postAs($author, $room, 'original body');

    Livewire::test('bonfire::message-list', ['room' => $room])
        ->call('editMessage', $message->getKey(), str_repeat('a', 10001))
        ->assertHasErrors('body');

    expect($message->fresh()->body)->toBe('original body');
});

it('still edits a message to a normal-length body', function (): void {
    $admin = ibMember('Admin', BonfireRole::Admin);
    $room = Room::query()->create([
        'name' => 'Room '.Str::random(6),
        'slug' => Str::random(10),
        'type' => 0,
        'created_by' => $admin->getKey(),
    ]);

    $author = ibMember('Ada');
    $room->addMember($author);
    auth()->setUser($author->memberable);

    $message = Bonfire::postAs($author, $room, 'original body');

    Livewire::test('bonfire::message-list', ['room' => $room])
        ->call('editMessage', $message->getKey(), 'edited body')
        ->assertHasNoErrors();

    expect($message->fresh()->body)->toBe('edited body');
});
