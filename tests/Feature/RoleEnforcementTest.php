<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Livewire\Livewire;

it('lets a member delete their own message', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    $member = Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    $message = Bonfire::postAs($member, $room, 'oops');

    Livewire::test('bonfire::message-list', ['room' => $room])
        ->call('deleteMessage', $message->id);

    expect(Message::withTrashed()->find($message->id)->trashed())->toBeTrue();
});

it('prevents a member from deleting other members messages', function (): void {
    $room = aRoom();
    $author = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Grace']), 'Grace');

    $viewerUser = TestUser::query()->create(['name' => 'Ada']);
    Bonfire::ensureMember($viewerUser, 'Ada');
    auth()->setUser($viewerUser);

    $message = Bonfire::postAs($author, $room, 'protected');

    Livewire::test('bonfire::message-list', ['room' => $room])
        ->call('deleteMessage', $message->id)
        ->assertStatus(403);
});

it('lets moderators delete any message', function (): void {
    $room = aRoom();
    $author = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Grace']), 'Grace');

    $modUser = TestUser::query()->create(['name' => 'Mod']);
    Bonfire::ensureMember($modUser, 'Mod', null, BonfireRole::Moderator);
    auth()->setUser($modUser);

    $message = Bonfire::postAs($author, $room, 'spam');

    Livewire::test('bonfire::message-list', ['room' => $room])
        ->call('deleteMessage', $message->id);

    expect(Message::withTrashed()->find($message->id)->trashed())->toBeTrue();
});

it('gates the admin panel to admins', function (): void {
    $user = TestUser::query()->create(['name' => 'Ada']);
    Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    Livewire::test('bonfire::admin-panel')->assertStatus(403);
});

it('allows admins into the admin panel', function (): void {
    $user = TestUser::query()->create(['name' => 'Admin']);
    Bonfire::ensureMember($user, 'Admin', null, BonfireRole::Admin);
    auth()->setUser($user);

    Livewire::test('bonfire::admin-panel')->assertStatus(200);
});
