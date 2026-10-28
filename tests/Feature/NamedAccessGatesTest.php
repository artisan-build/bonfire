<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Events\MemberMentioned;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Attachment;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Mention;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Notifications\MentionedInMessage;
use ArtisanBuild\Bonfire\Support\AccessGateEvaluator;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function gateMember(string $name, BonfireRole $role = BonfireRole::Member): Member
{
    return Bonfire::ensureMember(TestUser::query()->create(['name' => $name]), $name, null, $role);
}

function gateRoom(?string $gate = 'paid'): Room
{
    $admin = gateMember('Admin '.Str::random(5), BonfireRole::Admin);

    return Room::query()->create([
        'name' => 'Gate room '.Str::random(5),
        'slug' => Str::random(10),
        'type' => 0,
        'access_gate' => $gate,
        'created_by' => $admin->getKey(),
    ]);
}

function gateMessage(Room $room, Member $member, array $attributes = []): Message
{
    return Message::withoutEvents(fn (): Message => Message::query()->create(array_merge([
        'room_id' => $room->getKey(),
        'member_id' => $member->getKey(),
        'body' => 'secret',
    ], $attributes)));
}

it('fails closed for missing and throwing access gate resolvers', function (): void {
    $member = gateMember('Ada');
    $missing = gateRoom('missing');
    $throwing = gateRoom('throwing');

    Bonfire::gate('throwing', function (): bool {
        throw new RuntimeException('resolver failed');
    });

    expect($missing->isAccessibleBy($member))->toBeFalse()
        ->and($throwing->isAccessibleBy($member))->toBeFalse();
});

it('requires a literal true from an access gate resolver', function (): void {
    $member = gateMember('Ada');
    $room = gateRoom('truthy');

    Bonfire::gate('truthy', fn (Member $resolved) => 1);

    expect($room->isAccessibleBy($member))->toBeFalse();
});

it('resets console gate memoization with the scoped evaluator lifecycle', function (): void {
    $member = gateMember('Ada');
    $allowed = true;
    $calls = 0;

    Bonfire::gate('paid', function (Member $resolved) use (&$allowed, &$calls, $member): bool {
        $calls++;

        return $resolved->is($member) && $allowed;
    });

    $first = app(AccessGateEvaluator::class);

    expect(app()->runningInConsole())->toBeTrue()
        ->and($first->allows('paid', $member))->toBeTrue()
        ->and($calls)->toBe(1);

    $allowed = false;
    app()->forgetScopedInstances();

    $second = app(AccessGateEvaluator::class);

    expect($second)->not->toBe($first)
        ->and($second->allows('paid', $member))->toBeFalse()
        ->and($calls)->toBe(2);
});

it('leaves ungated room behavior unchanged', function (): void {
    $member = gateMember('Ada');
    $room = gateRoom(null);

    expect($room->isAccessibleBy($member))->toBeTrue();

    $message = Bonfire::postAs($member, $room, 'hello');

    expect($message->body)->toBe('hello');
});

it('memoizes one resolver result across rooms in a request and recomputes next request', function (): void {
    [$user, $member] = apiActor('Ada');
    $first = gateRoom();
    $second = gateRoom();
    $first->addMember($member);
    $second->addMember($member);

    $allowed = true;
    $calls = 0;

    Bonfire::gate('paid', function (Member $resolved) use (&$allowed, &$calls, $member): bool {
        $calls++;

        return $resolved->is($member) && $allowed;
    });

    Sanctum::actingAs($user, ['*']);

    $beforePivots = DB::table('bonfire_member_room')->count();
    $firstResponse = $this->getJson(route('bonfire.api.rooms.index'));

    $firstResponse->assertOk();
    expect(collect($firstResponse->json('data'))->pluck('id')->all())
        ->toContain($first->id)
        ->toContain($second->id);
    expect($calls)->toBe(1);

    $allowed = false;
    $secondResponse = $this->getJson(route('bonfire.api.rooms.index'));

    $secondResponse->assertOk()->assertJsonCount(0, 'data');
    expect($calls)->toBe(2)
        ->and(DB::table('bonfire_member_room')->count())->toBe($beforePivots);
});

it('denies gated room and message API families without changing state', function (): void {
    [$user, $member] = apiActor('Ada', BonfireRole::Admin);
    $target = gateMember('Grace');
    $room = gateRoom();
    $room->addMember($member);
    $message = gateMessage($room, $member, [
        'poll' => ['question' => 'Choose', 'options' => ['A', 'B']],
    ]);

    Bonfire::gate('paid', fn (Member $resolved): bool => false);
    Sanctum::actingAs($user, ['*']);

    $this->getJson(route('bonfire.api.rooms.index'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
    $this->getJson(route('bonfire.api.rooms.show', $room))->assertForbidden();
    $this->getJson(route('bonfire.api.rooms.messages.index', $room))->assertForbidden();
    $this->postJson(route('bonfire.api.rooms.messages.store', $room), ['body' => 'blocked'])->assertForbidden();
    $this->patchJson(route('bonfire.api.messages.update', $message), ['body' => 'blocked'])->assertForbidden();
    $this->deleteJson(route('bonfire.api.messages.destroy', $message))->assertForbidden();
    $this->postJson(route('bonfire.api.messages.reactions.store', $message), ['emoji' => '👍'])->assertForbidden();
    $this->postJson(route('bonfire.api.messages.poll.vote', $message), ['option_index' => 0])->assertForbidden();
    $this->postJson(route('bonfire.api.rooms.read', $room))->assertForbidden();
    $this->postJson(route('bonfire.api.rooms.members.store', $room), ['member_id' => $target->id])->assertForbidden();
    $this->deleteJson(route('bonfire.api.rooms.members.destroy', [$room, $target]))->assertForbidden();

    expect($message->fresh()->body)->toBe('secret')
        ->and(Message::query()->where('body', 'blocked')->exists())->toBeFalse()
        ->and(DB::table('bonfire_reactions')->count())->toBe(0)
        ->and(DB::table('bonfire_poll_votes')->count())->toBe(0)
        ->and(DB::table('bonfire_room_reads')->count())->toBe(0);
});

it('applies the registered gate to every room and message policy ability', function (): void {
    $member = gateMember('Ada');
    $room = gateRoom();
    $message = gateMessage($room, $member);
    $user = $member->memberable;

    Bonfire::gate('paid', fn (Member $resolved): bool => false);

    expect(Gate::forUser($user)->allows('view', $room))->toBeFalse()
        ->and(Gate::forUser($user)->allows('post', $room))->toBeFalse()
        ->and(Gate::forUser($user)->allows('forwardInto', $room))->toBeFalse()
        ->and(Gate::forUser($user)->allows('useRealtime', $room))->toBeFalse()
        ->and(Gate::forUser($user)->allows('update', $message))->toBeFalse()
        ->and(Gate::forUser($user)->allows('delete', $message))->toBeFalse()
        ->and(Gate::forUser($user)->allows('pin', $message))->toBeFalse()
        ->and(Gate::forUser($user)->allows('react', $message))->toBeFalse()
        ->and(Gate::forUser($user)->allows('vote', $message))->toBeFalse();
});

it('denies anonymous downloads from a gated public room', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('bonfire/attachments/secret.txt', 'secret');

    $author = gateMember('Ada');
    $room = gateRoom();
    $message = gateMessage($room, $author);
    $attachment = Attachment::query()->create([
        'message_id' => $message->id,
        'disk' => 'public',
        'path' => 'bonfire/attachments/secret.txt',
        'filename' => 'secret.txt',
        'mime_type' => 'text/plain',
        'size' => 6,
        'created_at' => now(),
    ]);

    Bonfire::gate('paid', fn (Member $resolved): bool => true);

    $this->get(route('bonfire.attachments.show', $attachment))->assertForbidden();
});

it('suppresses mention rows notifications and broadcasts for denied recipients', function (string $body): void {
    Notification::fake();
    Event::fake([MemberMentioned::class]);

    $author = gateMember('Ada');
    $target = gateMember('Grace');
    $room = gateRoom();
    $room->addMember($target);

    Bonfire::gate('paid', fn (Member $member): bool => $member->is($author));

    Bonfire::postAs($author, $room, $body);

    expect(Mention::query()->where('member_id', $target->id)->exists())->toBeFalse();
    Notification::assertNotSentTo($target, MentionedInMessage::class);
    Event::assertNotDispatched(MemberMentioned::class, fn (MemberMentioned $event): bool => $event->mentioned->is($target));
})->with([
    'explicit mention' => 'Hello @Grace',
    'everyone mention' => 'Hello @everyone',
    'channel mention' => 'Hello @channel',
]);
