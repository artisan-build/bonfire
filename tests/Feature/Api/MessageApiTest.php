<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Models\Message;
use Laravel\Sanctum\Sanctum;

/**
 * Seed N messages in a room authored by the given member, returning their ids
 * in creation order.
 *
 * @return list<int>
 */
function seedMessages(int $roomId, int $memberId, int $count): array
{
    $ids = [];

    for ($i = 1; $i <= $count; $i++) {
        $ids[] = (int) Message::query()->create([
            'room_id' => $roomId,
            'member_id' => $memberId,
            'body' => "message {$i}",
        ])->getKey();
    }

    return $ids;
}

it('cursor-paginates room messages newest-first with disjoint pages', function (): void {
    [$user, $member] = apiActor('Ada');
    [$room] = apiRoom();
    $room->addMember($member);

    $ids = seedMessages($room->id, $member->id, 20);
    rsort($ids); // expected newest-first order (id desc)

    Sanctum::actingAs($user, ['*']);

    $page1 = $this->getJson(route('bonfire.api.rooms.messages.index', $room));
    $page1->assertOk();

    $nextCursor = $page1->json('meta.next_cursor');
    expect($nextCursor)->not->toBeNull();

    $page1Ids = collect($page1->json('data'))->pluck('id')->all();

    $page2 = $this->getJson(route('bonfire.api.rooms.messages.index', [$room, 'cursor' => $nextCursor]));
    $page2->assertOk();
    $page2Ids = collect($page2->json('data'))->pluck('id')->all();

    // Pages are disjoint and together cover every message in stable id-desc order.
    expect(array_intersect($page1Ids, $page2Ids))->toBe([])
        ->and([...$page1Ids, ...$page2Ids])->toBe($ids);
});

it('polls forward returning only messages after the cursor, ascending', function (): void {
    [$user, $member] = apiActor('Ada');
    [$room] = apiRoom();
    $room->addMember($member);

    $ids = seedMessages($room->id, $member->id, 5);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('bonfire.api.rooms.messages.index', [$room, 'after' => $ids[2]]));
    $response->assertOk();

    $returned = collect($response->json('data'))->pluck('id')->all();

    expect($returned)->toBe([$ids[3], $ids[4]])
        ->and(array_intersect($returned, [$ids[0], $ids[1], $ids[2]]))->toBe([]);
});

it('returns empty when no messages are newer than the cursor', function (): void {
    [$user, $member] = apiActor('Ada');
    [$room] = apiRoom();
    $room->addMember($member);

    $ids = seedMessages($room->id, $member->id, 5);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('bonfire.api.rooms.messages.index', [$room, 'after' => max($ids)]));
    $response->assertOk();

    expect($response->json('data'))->toBe([]);
});

it('rejects a non-integer after cursor', function (): void {
    [$user, $member] = apiActor('Ada');
    [$room] = apiRoom();
    $room->addMember($member);

    Sanctum::actingAs($user, ['*']);

    $this->getJson(route('bonfire.api.rooms.messages.index', [$room, 'after' => 'abc']))->assertStatus(422);
});

it('still forbids forward-polling a private room for a non-member', function (): void {
    [$user] = apiActor('Outsider');
    [$room] = apiRoom(RoomType::Private->value);

    Sanctum::actingAs($user, ['*']);

    $this->getJson(route('bonfire.api.rooms.messages.index', [$room, 'after' => 1]))->assertForbidden();
});

it('includes replies and exposes parent_id for threading', function (): void {
    [$user, $member] = apiActor('Ada');
    [$room] = apiRoom();
    $room->addMember($member);

    $root = Message::query()->create(['room_id' => $room->id, 'member_id' => $member->id, 'body' => 'root']);
    $reply = Message::query()->create(['room_id' => $room->id, 'member_id' => $member->id, 'parent_id' => $root->id, 'body' => 'reply']);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('bonfire.api.rooms.messages.index', $room));
    $byId = collect($response->json('data'))->keyBy('id');

    expect($byId)->toHaveKeys([$root->id, $reply->id])
        ->and($byId[$reply->id]['parent_id'])->toBe($root->id)
        ->and($byId[$root->id]['parent_id'])->toBeNull();
});

it('forbids reading messages in a private room the member is not in', function (): void {
    [$user] = apiActor('Outsider');
    [$room] = apiRoom(RoomType::Private->value);

    Sanctum::actingAs($user, ['*']);

    $this->getJson(route('bonfire.api.rooms.messages.index', $room))->assertForbidden();
});

it('posts a message and returns the raw markdown verbatim', function (): void {
    [$user, $member] = apiActor('Ada');
    [$room] = apiRoom();
    $room->addMember($member);

    $body = '**bold** and _italic_ and `code`';

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson(route('bonfire.api.rooms.messages.store', $room), ['body' => $body]);

    $response->assertCreated()
        ->assertJsonPath('data.body', $body)
        ->assertJsonPath('data.room_id', $room->id)
        ->assertJsonPath('data.member.id', $member->id);

    // Raw markdown, never converted to HTML.
    expect($response->json('data.body'))->toBe($body)
        ->not->toContain('<strong>')
        ->not->toContain('<em>');

    $this->assertDatabaseHas('bonfire_messages', ['room_id' => $room->id, 'body' => $body]);
});

it('posts a reply that belongs to the same room', function (): void {
    [$user, $member] = apiActor('Ada');
    [$room] = apiRoom();
    $room->addMember($member);
    $root = Message::query()->create(['room_id' => $room->id, 'member_id' => $member->id, 'body' => 'root']);

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('bonfire.api.rooms.messages.store', $room), [
        'body' => 'a reply',
        'parent_id' => $root->id,
    ])->assertCreated()->assertJsonPath('data.parent_id', $root->id);
});

it('rejects a parent message from another room', function (): void {
    [$user, $member] = apiActor('Ada');
    [$room] = apiRoom();
    [$otherRoom] = apiRoom();
    $room->addMember($member);
    $foreign = Message::query()->create(['room_id' => $otherRoom->id, 'member_id' => $member->id, 'body' => 'elsewhere']);

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('bonfire.api.rooms.messages.store', $room), [
        'body' => 'bad reply',
        'parent_id' => $foreign->id,
    ])->assertStatus(422);
});

it('creates a poll message via the API', function (): void {
    [$user, $member] = apiActor('Ada');
    [$room] = apiRoom();
    $room->addMember($member);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson(route('bonfire.api.rooms.messages.store', $room), [
        'body' => 'vote now',
        'poll' => ['question' => 'Lunch?', 'options' => ['Tacos', 'Pizza']],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.poll.question', 'Lunch?')
        ->assertJsonPath('data.poll.options.0.text', 'Tacos')
        ->assertJsonPath('data.poll.my_vote', null);
});

it('edits a message and returns the updated body', function (): void {
    [$user, $member] = apiActor('Ada');
    [$room] = apiRoom();
    $room->addMember($member);
    $message = Message::query()->create(['room_id' => $room->id, 'member_id' => $member->id, 'body' => 'before']);

    Sanctum::actingAs($user, ['*']);

    $this->patchJson(route('bonfire.api.messages.update', $message), ['body' => 'after **edit**'])
        ->assertOk()
        ->assertJsonPath('data.body', 'after **edit**');

    $this->assertDatabaseHas('bonfire_messages', ['id' => $message->id, 'body' => 'after **edit**']);
});

it('forbids editing another member\'s message', function (): void {
    [$ownerUser, $owner] = apiActor('Owner');
    [$otherUser] = apiActor('Other');
    [$room] = apiRoom();
    $room->addMember($owner);
    $message = Message::query()->create(['room_id' => $room->id, 'member_id' => $owner->id, 'body' => 'mine']);

    Sanctum::actingAs($otherUser, ['*']);

    $this->patchJson(route('bonfire.api.messages.update', $message), ['body' => 'hijacked'])->assertForbidden();
});

it('soft-deletes a message authored by the member', function (): void {
    [$user, $member] = apiActor('Ada');
    [$room] = apiRoom();
    $room->addMember($member);
    $message = Message::query()->create(['room_id' => $room->id, 'member_id' => $member->id, 'body' => 'doomed']);

    Sanctum::actingAs($user, ['*']);

    $this->deleteJson(route('bonfire.api.messages.destroy', $message))->assertNoContent();

    $this->assertSoftDeleted('bonfire_messages', ['id' => $message->id]);
});

it('forbids deleting another member\'s message', function (): void {
    [, $owner] = apiActor('Owner');
    [$otherUser] = apiActor('Other');
    [$room] = apiRoom();
    $room->addMember($owner);
    $message = Message::query()->create(['room_id' => $room->id, 'member_id' => $owner->id, 'body' => 'mine']);

    Sanctum::actingAs($otherUser, ['*']);

    $this->deleteJson(route('bonfire.api.messages.destroy', $message))->assertForbidden();
});

it('toggles a reaction on then off', function (): void {
    [$user, $member] = apiActor('Ada');
    [$room] = apiRoom();
    $room->addMember($member);
    $message = Message::query()->create(['room_id' => $room->id, 'member_id' => $member->id, 'body' => 'react to me']);

    Sanctum::actingAs($user, ['*']);

    $first = $this->postJson(route('bonfire.api.messages.reactions.store', $message), ['emoji' => '👍']);
    $first->assertOk()->assertJsonPath('data.reactions.0.emoji', '👍')
        ->assertJsonPath('data.reactions.0.count', 1)
        ->assertJsonPath('data.reactions.0.reacted', true);

    $this->assertDatabaseHas('bonfire_reactions', ['message_id' => $message->id, 'member_id' => $member->id, 'emoji' => '👍']);

    $second = $this->postJson(route('bonfire.api.messages.reactions.store', $message), ['emoji' => '👍']);
    $second->assertOk();
    expect($second->json('data.reactions'))->toBe([]);

    $this->assertDatabaseMissing('bonfire_reactions', ['message_id' => $message->id, 'member_id' => $member->id, 'emoji' => '👍']);
});

it('toggles a poll vote on then off and reports state', function (): void {
    [$user, $member] = apiActor('Ada');
    [$room] = apiRoom();
    $room->addMember($member);
    $message = Message::query()->create([
        'room_id' => $room->id,
        'member_id' => $member->id,
        'body' => 'Lunch?',
        'poll' => ['question' => 'Lunch?', 'options' => ['Tacos', 'Pizza']],
    ]);

    Sanctum::actingAs($user, ['*']);

    $voted = $this->postJson(route('bonfire.api.messages.poll.vote', $message), ['option_index' => 1]);
    $voted->assertOk()
        ->assertJsonPath('data.poll.my_vote', 1)
        ->assertJsonPath('data.poll.options.1.votes', 1)
        ->assertJsonPath('data.poll.options.1.voted', true);

    $this->assertDatabaseHas('bonfire_poll_votes', ['message_id' => $message->id, 'member_id' => $member->id, 'option_index' => 1]);

    $unvoted = $this->postJson(route('bonfire.api.messages.poll.vote', $message), ['option_index' => 1]);
    $unvoted->assertOk()->assertJsonPath('data.poll.my_vote', null);

    $this->assertDatabaseMissing('bonfire_poll_votes', ['message_id' => $message->id, 'member_id' => $member->id]);
});

it('rejects an out-of-range poll vote', function (): void {
    [$user, $member] = apiActor('Ada');
    [$room] = apiRoom();
    $room->addMember($member);
    $message = Message::query()->create([
        'room_id' => $room->id,
        'member_id' => $member->id,
        'body' => 'Lunch?',
        'poll' => ['question' => 'Lunch?', 'options' => ['Tacos', 'Pizza']],
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('bonfire.api.messages.poll.vote', $message), ['option_index' => 5])->assertStatus(422);
});

it('whitelists member fields and never leaks raw columns', function (): void {
    [$user, $member] = apiActor('Ada');
    $member->update(['phone' => '555-1234']);
    [$room] = apiRoom();
    $room->addMember($member);
    Message::query()->create(['room_id' => $room->id, 'member_id' => $member->id, 'body' => 'hello']);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('bonfire.api.rooms.messages.index', $room));
    $memberJson = $response->json('data.0.member');

    expect(array_keys($memberJson))->toEqualCanonicalizing(['id', 'display_name', 'avatar_url', 'role', 'badges'])
        ->and($memberJson['badges'])->toBe([])
        ->and($memberJson)->not->toHaveKey('phone')
        ->and($memberJson)->not->toHaveKey('memberable_type')
        ->and($memberJson)->not->toHaveKey('memberable_id');
});
