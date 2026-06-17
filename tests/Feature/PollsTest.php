<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\PollVote;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Livewire\Livewire;

/**
 * Poll creation flows through the Poll modal — createPoll()/stagePoll() on the
 * message-composer component — not slash-command parsing. The /poll text
 * shortcut was intentionally removed (commit 07e0cd2) because of debounce,
 * Tiptap-sync, and toast issues. See the "restore /poll slash-command" issue.
 */
it('creates a poll via createPoll', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('pollQuestion', 'Lunch?')
        ->set('pollOptions', ['Pizza', 'Tacos', 'Salad'])
        ->call('createPoll');

    $message = Message::query()->first();

    expect($message)->not->toBeNull()
        ->and($message->isPoll())->toBeTrue()
        ->and($message->body)->toBe('Lunch?')
        ->and($message->poll['question'])->toBe('Lunch?')
        ->and($message->poll['options'])->toBe(['Pizza', 'Tacos', 'Salad']);
});

it('keeps a typed text body alongside the poll', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('body', '<p>Vote now!</p>')
        ->set('pollQuestion', 'Lunch?')
        ->set('pollOptions', ['Pizza', 'Tacos', 'Salad'])
        ->call('createPoll');

    $message = Message::query()->first();

    expect($message)->not->toBeNull()
        ->and($message->isPoll())->toBeTrue()
        ->and($message->body)->toBe('<p>Vote now!</p>')
        ->and($message->poll['question'])->toBe('Lunch?');
});

it('stages a poll then sends it via send()', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    $composer = Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('pollQuestion', 'Lunch?')
        ->set('pollOptions', ['Pizza', 'Tacos', 'Salad'])
        ->call('stagePoll');

    expect($composer->get('pendingPoll'))->not->toBeNull();

    $composer->call('send');

    $message = Message::query()->first();

    expect($message)->not->toBeNull()
        ->and($message->isPoll())->toBeTrue()
        ->and($message->poll['question'])->toBe('Lunch?')
        ->and($message->poll['options'])->toBe(['Pizza', 'Tacos', 'Salad']);
});

it('rejects a poll with fewer than two non-empty options', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('pollQuestion', 'Only a question')
        ->set('pollOptions', ['Single option', ''])
        ->call('createPoll')
        ->assertHasErrors('pollOptions');

    expect(Message::query()->count())->toBe(0);
});

it('records a vote via togglePollVote', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    $member = Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('pollQuestion', 'Lunch?')
        ->set('pollOptions', ['Pizza', 'Tacos', 'Salad'])
        ->call('createPoll');

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
        ->set('pollQuestion', 'Lunch?')
        ->set('pollOptions', ['Pizza', 'Tacos', 'Salad'])
        ->call('createPoll');

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
        ->set('pollQuestion', 'Lunch?')
        ->set('pollOptions', ['Pizza', 'Tacos', 'Salad'])
        ->call('createPoll');

    $poll = Message::query()->first();

    $list = Livewire::test('bonfire::message-list', ['room' => $room]);
    $list->call('togglePollVote', $poll->id, 1);
    $list->call('togglePollVote', $poll->id, 1);

    expect(PollVote::query()->count())->toBe(0);
});
