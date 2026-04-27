<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\PollVote;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Livewire\Livewire;

it('creates a poll from a /poll command', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('body', '/poll Lunch? | Pizza | Tacos | Salad')
        ->call('send');

    $message = Message::query()->first();

    expect($message)->not->toBeNull()
        ->and($message->isPoll())->toBeTrue()
        ->and($message->body)->toBe('Lunch?')
        ->and($message->poll['question'])->toBe('Lunch?')
        ->and($message->poll['options'])->toBe(['Pizza', 'Tacos', 'Salad']);
});

it('creates a poll from HTML-wrapped body (Tiptap output)', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('body', '<p>/poll Lunch? | Pizza | Tacos | Salad</p>')
        ->call('send');

    $message = Message::query()->first();

    expect($message)->not->toBeNull()
        ->and($message->isPoll())->toBeTrue()
        ->and($message->poll['question'])->toBe('Lunch?');
});

it('creates a poll when question contains HTML entities', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('body', '<p>/poll What&#39;s for lunch? | Pizza | Tacos | Salad</p>')
        ->call('send');

    $message = Message::query()->first();

    expect($message)->not->toBeNull()
        ->and($message->isPoll())->toBeTrue()
        ->and($message->poll['question'])->toBe("What's for lunch?");
});

it('ignores /poll without enough options', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('body', '/poll Only a question | Single option')
        ->call('send');

    $message = Message::query()->first();

    expect($message)->not->toBeNull()
        ->and($message->isPoll())->toBeFalse();
});

it('records a vote via togglePollVote', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    $member = Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('body', '/poll Lunch? | Pizza | Tacos | Salad')
        ->call('send');

    $poll = Message::query()->first();

    Livewire::test('bonfire::message-list', ['room' => $room])
        ->call('togglePollVote', $poll->id, 1);

    expect(PollVote::query()->count())->toBe(1)
        ->and(PollVote::query()->first()->option_index)->toBe(1)
        ->and(PollVote::query()->first()->member_id)->toBe($member->id);
});

it('switches a vote to a different option', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('body', '/poll Lunch? | Pizza | Tacos | Salad')
        ->call('send');

    $poll = Message::query()->first();

    $list = Livewire::test('bonfire::message-list', ['room' => $room]);
    $list->call('togglePollVote', $poll->id, 0);
    $list->call('togglePollVote', $poll->id, 2);

    expect(PollVote::query()->count())->toBe(1)
        ->and(PollVote::query()->first()->option_index)->toBe(2);
});

it('removes a vote when toggled on the same option', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('body', '/poll Lunch? | Pizza | Tacos | Salad')
        ->call('send');

    $poll = Message::query()->first();

    $list = Livewire::test('bonfire::message-list', ['room' => $room]);
    $list->call('togglePollVote', $poll->id, 1);
    $list->call('togglePollVote', $poll->id, 1);

    expect(PollVote::query()->count())->toBe(0);
});
