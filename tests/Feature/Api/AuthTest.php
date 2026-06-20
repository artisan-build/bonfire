<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Models\Message;

/**
 * Build a valid room + message + member graph so each URL resolves; the point
 * of these cases is that the auth:sanctum middleware rejects before anything.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function unauthenticatedEndpoints(): array
{
    [$room, $admin] = apiRoom();
    [, $member] = apiActor('Ada');
    $room->addMember($member);

    $message = Message::query()->create([
        'room_id' => $room->getKey(),
        'member_id' => $member->getKey(),
        'body' => 'hello',
    ]);

    return [
        'rooms index' => ['get', route('bonfire.api.rooms.index')],
        'room show' => ['get', route('bonfire.api.rooms.show', $room)],
        'messages index' => ['get', route('bonfire.api.rooms.messages.index', $room)],
        'messages store' => ['post', route('bonfire.api.rooms.messages.store', $room)],
        'message update' => ['patch', route('bonfire.api.messages.update', $message)],
        'message destroy' => ['delete', route('bonfire.api.messages.destroy', $message)],
        'message react' => ['post', route('bonfire.api.messages.reactions.store', $message)],
        'message vote' => ['post', route('bonfire.api.messages.poll.vote', $message)],
        'room read' => ['post', route('bonfire.api.rooms.read', $room)],
        'room members store' => ['post', route('bonfire.api.rooms.members.store', $room)],
        'room members destroy' => ['delete', route('bonfire.api.rooms.members.destroy', [$room, $member])],
    ];
}

it('rejects every endpoint without authentication', function (): void {
    foreach (unauthenticatedEndpoints() as $name => [$method, $url]) {
        $response = $this->json($method, $url);

        expect($response->getStatusCode())
            ->toBe(401, "Expected 401 for {$name} ({$method} {$url}), got {$response->getStatusCode()}");
    }
});
