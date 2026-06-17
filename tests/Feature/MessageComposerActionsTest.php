<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Actions\CancelScheduledMessage;
use ArtisanBuild\Bonfire\Actions\ReleaseDueScheduledMessages;
use ArtisanBuild\Bonfire\Actions\SendMessage;
use ArtisanBuild\Bonfire\Actions\SendScheduledMessageNow;
use ArtisanBuild\Bonfire\Actions\UpdateScheduledMessage;
use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Events\MessagePosted;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Attachment;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function mcaMember(string $name): Member
{
    return Bonfire::ensureMember(TestUser::query()->create(['name' => $name]), $name);
}

function mcaRoom(int $type = 0): Room
{
    $admin = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Admin']), 'Admin', null, BonfireRole::Admin);

    return Room::query()->create([
        'name' => 'Room '.Str::random(6),
        'slug' => Str::random(8),
        'type' => $type,
        'created_by' => $admin->getKey(),
    ]);
}

/**
 * Create a future-scheduled message directly (no events fired), mirroring how
 * the composer persists scheduled messages.
 */
function mcaScheduled(Member $member, Room $room, Carbon $when, string $body = 'later'): Message
{
    return Message::withoutEvents(fn (): Message => Message::query()->create([
        'room_id' => $room->getKey(),
        'member_id' => $member->getKey(),
        'body' => $body,
        'scheduled_for' => $when,
    ]));
}

it('persists scheduled_for and does not fire MessagePosted for a scheduled send', function (): void {
    Event::fake([MessagePosted::class]);

    $room = mcaRoom();
    $member = mcaMember('Ada');
    $when = now()->addDay();

    $message = app(SendMessage::class)(
        $member, $room, 'see you tomorrow', null, $when, null, [], [], false,
    );

    expect($message->scheduled_for)->not->toBeNull()
        ->and($message->scheduled_for->toIso8601String())->toBe($when->toIso8601String());

    Event::assertNotDispatched(MessagePosted::class);
});

it('fires MessagePosted for an immediate send', function (): void {
    Event::fake([MessagePosted::class]);

    $room = mcaRoom();
    $member = mcaMember('Ada');

    $message = app(SendMessage::class)(
        $member, $room, 'hello now', null, null, null, [], [], false,
    );

    expect($message->scheduled_for)->toBeNull()
        ->and($message->body)->toBe('hello now');

    Event::assertDispatched(MessagePosted::class);
});

it('persists the poll as an array on the message', function (): void {
    $room = mcaRoom();
    $member = mcaMember('Ada');
    $poll = ['question' => 'Lunch?', 'options' => ['Pizza', 'Tacos', 'Salad']];

    $message = app(SendMessage::class)(
        $member, $room, 'Lunch?', null, null, $poll, [], [], false,
    );

    expect($message->isPoll())->toBeTrue()
        ->and($message->poll)->toBe($poll)
        ->and($message->poll['options'])->toBe(['Pizza', 'Tacos', 'Salad']);
});

it('clones an existing attachment only when it belongs to the member', function (): void {
    Storage::fake('public');

    $room = mcaRoom();
    $owner = mcaMember('Ada');
    $other = mcaMember('Bob');

    $ownerMessage = Bonfire::postAs($owner, $room, 'mine');
    $ownedAttachment = Attachment::query()->create([
        'message_id' => $ownerMessage->id,
        'disk' => 'public',
        'path' => 'bonfire/attachments/owned.txt',
        'filename' => 'owned.txt',
        'mime_type' => 'text/plain',
        'size' => 1,
        'created_at' => now(),
    ]);

    $otherMessage = Bonfire::postAs($other, $room, 'theirs');
    $foreignAttachment = Attachment::query()->create([
        'message_id' => $otherMessage->id,
        'disk' => 'public',
        'path' => 'bonfire/attachments/foreign.txt',
        'filename' => 'foreign.txt',
        'mime_type' => 'text/plain',
        'size' => 1,
        'created_at' => now(),
    ]);

    $message = app(SendMessage::class)(
        $owner, $room, 'with clones', null, null, null, [],
        [$ownedAttachment->id, $foreignAttachment->id], false,
    );

    $cloned = Attachment::query()->where('message_id', $message->id)->get();

    expect($cloned)->toHaveCount(1)
        ->and($cloned->first()->path)->toBe('bonfire/attachments/owned.txt');
});

it('stores an uploaded file as an attachment', function (): void {
    Storage::fake('public');

    $room = mcaRoom();
    $member = mcaMember('Ada');
    $upload = UploadedFile::fake()->create('notes.txt', 2, 'text/plain');

    $message = app(SendMessage::class)(
        $member, $room, 'with upload', null, null, null, [$upload], [], false,
    );

    $stored = Attachment::query()->where('message_id', $message->id)->first();

    expect($stored)->not->toBeNull()
        ->and($stored->filename)->toBe('notes.txt');
    Storage::disk('public')->assertExists($stored->path);
});

it('posts a second channel copy for a reply when also-send-to-channel and not scheduled', function (): void {
    $room = mcaRoom();
    $member = mcaMember('Ada');
    $parent = Bonfire::postAs($member, $room, 'parent');

    $reply = app(SendMessage::class)(
        $member, $room, 'in reply', $parent, null, null, [], [], true,
    );

    expect($reply->parent_id)->toBe($parent->getKey());

    $channelCopy = Message::query()
        ->where('body', 'in reply')
        ->whereNull('parent_id')
        ->first();

    expect($channelCopy)->not->toBeNull();
});

it('does not post a channel copy for a scheduled reply', function (): void {
    $room = mcaRoom();
    $member = mcaMember('Ada');
    $parent = Bonfire::postAs($member, $room, 'parent');

    app(SendMessage::class)(
        $member, $room, 'scheduled reply', $parent, now()->addDay(), null, [], [], true,
    );

    $channelCopy = Message::query()
        ->where('body', 'scheduled reply')
        ->whereNull('parent_id')
        ->first();

    expect($channelCopy)->toBeNull();
});

it('releases a due owned scheduled message and fires MessagePosted', function (): void {
    Event::fake([MessagePosted::class]);

    $room = mcaRoom();
    $member = mcaMember('Ada');
    $other = mcaMember('Bob');

    $dueAt = now()->subMinute();
    $due = mcaScheduled($member, $room, $dueAt, 'due');
    $notDue = mcaScheduled($member, $room, now()->addDay(), 'pending');
    $othersDue = mcaScheduled($other, $room, now()->subMinute(), 'theirs');

    $released = app(ReleaseDueScheduledMessages::class)($member);

    expect($released)->toHaveCount(1);

    $due->refresh();
    expect($due->scheduled_for)->toBeNull()
        ->and($due->created_at->toIso8601String())->toBe($dueAt->toIso8601String());

    $notDue->refresh();
    expect($notDue->scheduled_for)->not->toBeNull();

    $othersDue->refresh();
    expect($othersDue->scheduled_for)->not->toBeNull();

    Event::assertDispatched(MessagePosted::class, fn (MessagePosted $event): bool => $event->message->is($due));
});

it('updates only the member\'s own future scheduled message', function (): void {
    $room = mcaRoom();
    $member = mcaMember('Ada');
    $other = mcaMember('Bob');

    $message = mcaScheduled($member, $room, now()->addDay());

    $updated = app(UpdateScheduledMessage::class)($member, $message->id, 'edited', now()->addDays(2));
    expect($updated)->not->toBeNull()
        ->and($updated->body)->toBe('edited');

    // Another member cannot update it.
    expect(app(UpdateScheduledMessage::class)($other, $message->id, 'hijack', now()->addDays(3)))->toBeNull();

    // A new time in the past is rejected.
    expect(app(UpdateScheduledMessage::class)($member, $message->id, 'past', now()->subDay()))->toBeNull();

    $message->refresh();
    expect($message->body)->toBe('edited');
});

it('cancels only the member\'s own future scheduled message', function (): void {
    $room = mcaRoom();
    $member = mcaMember('Ada');
    $other = mcaMember('Bob');

    $message = mcaScheduled($member, $room, now()->addDay());

    // Another member's attempt is a no-op.
    expect(app(CancelScheduledMessage::class)($other, $message->id))->toBeNull();
    expect(Message::query()->whereKey($message->id)->exists())->toBeTrue();

    expect(app(CancelScheduledMessage::class)($member, $message->id))->not->toBeNull();
    expect(Message::query()->whereKey($message->id)->exists())->toBeFalse();
});

it('sends now only the member\'s own future scheduled message', function (): void {
    $room = mcaRoom();
    $member = mcaMember('Ada');
    $other = mcaMember('Bob');

    $message = mcaScheduled($member, $room, now()->addDay());

    // Another member's attempt is a no-op.
    expect(app(SendScheduledMessageNow::class)($other, $message->id))->toBeNull();
    $message->refresh();
    expect($message->scheduled_for)->not->toBeNull();

    $sent = app(SendScheduledMessageNow::class)($member, $message->id);
    expect($sent)->not->toBeNull();

    $message->refresh();
    expect($message->scheduled_for)->toBeNull();
});

it('treats a past scheduled message as not found for lifecycle actions', function (): void {
    $room = mcaRoom();
    $member = mcaMember('Ada');

    $past = mcaScheduled($member, $room, now()->subDay());

    expect(app(CancelScheduledMessage::class)($member, $past->id))->toBeNull()
        ->and(app(SendScheduledMessageNow::class)($member, $past->id))->toBeNull()
        ->and(app(UpdateScheduledMessage::class)($member, $past->id, 'x', now()->addDay()))->toBeNull();
});
