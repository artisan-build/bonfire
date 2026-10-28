<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Actions\EditMessage;
use ArtisanBuild\Bonfire\Actions\ForwardMessage;
use ArtisanBuild\Bonfire\Actions\ReleaseDueScheduledMessages;
use ArtisanBuild\Bonfire\Actions\TogglePin;
use ArtisanBuild\Bonfire\Actions\TogglePollVote;
use ArtisanBuild\Bonfire\Actions\ToggleReaction;
use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Events\MessageEdited;
use ArtisanBuild\Bonfire\Events\MessagePosted;
use ArtisanBuild\Bonfire\Events\PollVoted;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\PollVote;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

function mlaMember(string $name): Member
{
    return Bonfire::ensureMember(TestUser::query()->create(['name' => $name]), $name);
}

function mlaRoom(int $type = 0): Room
{
    $admin = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Admin']), 'Admin', null, BonfireRole::Admin);

    return Room::query()->create([
        'name' => 'Room '.Str::random(6),
        'slug' => Str::random(8),
        'type' => $type,
        'created_by' => $admin->getKey(),
    ]);
}

function mlaScheduled(Member $member, Room $room, Carbon $when, string $body = 'later'): Message
{
    return Message::withoutEvents(fn (): Message => Message::query()->create([
        'room_id' => $room->getKey(),
        'member_id' => $member->getKey(),
        'body' => $body,
        'scheduled_for' => $when,
    ]));
}

it('adds, removes, and coexists reactions and broadcasts MessageEdited', function (): void {
    Event::fake([MessageEdited::class]);

    $room = mlaRoom();
    $member = mlaMember('Ada');
    $message = Bonfire::postAs($member, $room, 'hello');

    $rowCount = fn (): int => DB::table('bonfire_reactions')->where('message_id', $message->id)->count();

    app(ToggleReaction::class)($member, $message, '👍');
    expect($rowCount())->toBe(1);

    // Re-clicking the same emoji removes it.
    app(ToggleReaction::class)($member, $message, '👍');
    expect($rowCount())->toBe(0);

    // A different emoji coexists with the first.
    app(ToggleReaction::class)($member, $message, '👍');
    app(ToggleReaction::class)($member, $message, '🎉');
    expect($rowCount())->toBe(2);

    Event::assertDispatched(MessageEdited::class);
});

it('pins and unpins a message and broadcasts MessageEdited', function (): void {
    Event::fake([MessageEdited::class]);

    $room = mlaRoom();
    $member = mlaMember('Ada');
    $message = Bonfire::postAs($member, $room, 'pin me');

    app(TogglePin::class)($member, $message);

    $message->refresh();
    expect($message->pinned_at)->not->toBeNull()
        ->and($message->pinned_by_member_id)->toBe($member->getKey());

    app(TogglePin::class)($member, $message);

    $message->refresh();
    expect($message->pinned_at)->toBeNull()
        ->and($message->pinned_by_member_id)->toBeNull();

    Event::assertDispatched(MessageEdited::class);
});

it('updates a message body and broadcasts MessageEdited', function (): void {
    Event::fake([MessageEdited::class]);

    $room = mlaRoom();
    $member = mlaMember('Ada');
    $message = Bonfire::postAs($member, $room, 'original');

    app(EditMessage::class)($message, '<p>edited body</p>');

    expect($message->fresh()->body)->toBe('<p>edited body</p>');

    Event::assertDispatched(MessageEdited::class, fn (MessageEdited $event): bool => $event->message->is($message));
});

it('forwards a message into the target room with an escaped attribution blockquote', function (): void {
    app('router')->get('bonfire/{room:slug}', fn () => '')->name('bonfire.room.show');

    $sourceRoom = mlaRoom();
    $member = mlaMember('Ada & Co');
    $source = Bonfire::postAs($member, $sourceRoom, 'quoted body text');
    $source->load('member', 'room');

    $target = mlaRoom();

    $forwarded = app(ForwardMessage::class)($member, $source, $target, 'see this note');

    expect($forwarded->room_id)->toBe($target->getKey())
        ->and($forwarded->body)->toContain('data-bonfire-forward="'.$source->id.'"')
        ->and($forwarded->body)->toContain('Ada &amp; Co')
        ->and($forwarded->body)->toContain('quoted body text')
        ->and($forwarded->body)->toContain('see this note');

    // The note is omitted when not provided.
    $withoutNote = app(ForwardMessage::class)($member, $source, $target);
    expect($withoutNote->body)->not->toContain('see this note')
        ->and($withoutNote->body)->toContain('quoted body text');
});

it('caps the quoted forward body at 300 characters', function (): void {
    app('router')->get('bonfire/{room:slug}', fn () => '')->name('bonfire.room.show');

    $sourceRoom = mlaRoom();
    $member = mlaMember('Ada');
    $long = str_repeat('a', 500);
    $source = Bonfire::postAs($member, $sourceRoom, $long);
    $source->load('member', 'room');

    $target = mlaRoom();

    $forwarded = app(ForwardMessage::class)($member, $source, $target);

    expect($forwarded->body)->toContain(Str::limit($long, 300))
        ->and($forwarded->body)->not->toContain(str_repeat('a', 400));
});

it('records, removes, and switches poll votes and broadcasts PollVoted', function (): void {
    Event::fake([PollVoted::class]);

    $room = mlaRoom();
    $member = mlaMember('Ada');
    $poll = ['question' => 'Lunch?', 'options' => ['Pizza', 'Tacos', 'Salad']];
    $message = Message::query()->create([
        'room_id' => $room->getKey(),
        'member_id' => $member->getKey(),
        'body' => 'Lunch?',
        'poll' => $poll,
    ]);

    $votes = fn (): int => PollVote::query()->where('message_id', $message->id)->where('member_id', $member->id)->count();

    app(TogglePollVote::class)($member, $message, 0);
    expect($votes())->toBe(1)
        ->and(PollVote::query()->where('message_id', $message->id)->first()->option_index)->toBe(0);

    // Re-voting the same option removes it.
    app(TogglePollVote::class)($member, $message, 0);
    expect($votes())->toBe(0);

    // Switching options updates the single vote.
    app(TogglePollVote::class)($member, $message, 0);
    app(TogglePollVote::class)($member, $message, 1);
    expect($votes())->toBe(1)
        ->and((int) PollVote::query()->where('message_id', $message->id)->first()->option_index)->toBe(1);

    Event::assertDispatched(PollVoted::class);
});

it('releases only the scoped room\'s due scheduled messages', function (): void {
    Event::fake([MessagePosted::class]);

    $member = mlaMember('Ada');
    $roomA = mlaRoom();
    $roomB = mlaRoom();

    $dueAt = now()->subMinute();
    $dueInA = mlaScheduled($member, $roomA, $dueAt, 'due in A');
    $dueInB = mlaScheduled($member, $roomB, now()->subMinute(), 'due in B');

    $released = app(ReleaseDueScheduledMessages::class)(room: $roomA);

    expect($released)->toHaveCount(1);

    $dueInA->refresh();
    expect($dueInA->scheduled_for)->toBeNull()
        ->and($dueInA->created_at->toIso8601String())->toBe($dueAt->toIso8601String());

    // The other room's due message is untouched.
    $dueInB->refresh();
    expect($dueInB->scheduled_for)->not->toBeNull();

    Event::assertDispatched(MessagePosted::class, fn (MessagePosted $event): bool => $event->message->is($dueInA));
});

it('rejects a no-scope release to avoid an application-wide release', function (): void {
    expect(fn () => app(ReleaseDueScheduledMessages::class)())
        ->toThrow(InvalidArgumentException::class);
});
