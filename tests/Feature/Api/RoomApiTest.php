<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\RoomType;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('lists only the rooms the authenticated member belongs to', function (): void {
    [$user, $member] = apiActor('Ada');
    [$joined] = apiRoom();
    [$other] = apiRoom();
    $joined->addMember($member);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('bonfire.api.rooms.index'));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($joined->id)
        ->and($ids)->not->toContain($other->id);
});

it('forbids a deactivated member from listing rooms', function (): void {
    [$user, $member] = apiActor('Ada');
    [$room] = apiRoom();
    $room->addMember($member);
    $member->update(['is_active' => false]);

    Sanctum::actingAs($user, ['*']);

    $this->getJson(route('bonfire.api.rooms.index'))->assertForbidden();
});

it('exposes derived type flags and never the raw type integer', function (): void {
    [$user, $member] = apiActor('Ada');
    [$room] = apiRoom(RoomType::Private->value);
    $room->addMember($member);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('bonfire.api.rooms.show', $room));

    $response->assertOk()
        ->assertJsonPath('data.is_private', true)
        ->assertJsonPath('data.is_archived', false)
        ->assertJsonPath('data.is_announcement', false);

    expect($response->json('data'))->not->toHaveKey('type');
});

it('forbids viewing a private room the member does not belong to', function (): void {
    [$user] = apiActor('Outsider');
    [$room] = apiRoom(RoomType::Private->value);

    Sanctum::actingAs($user, ['*']);

    $this->getJson(route('bonfire.api.rooms.show', $room))->assertForbidden();
});

it('marks a room as read', function (): void {
    [$user, $member] = apiActor('Ada');
    [$room] = apiRoom();
    $room->addMember($member);

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('bonfire.api.rooms.read', $room))->assertNoContent();

    expect(DB::table('bonfire_room_reads')
        ->where('room_id', $room->id)
        ->where('member_id', $member->id)
        ->count())->toBe(1);
});
