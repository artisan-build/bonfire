<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('adds a member to a room as an admin', function (): void {
    [$adminUser] = apiActor('Boss', BonfireRole::Admin);
    [, $target] = apiActor('Ada');
    [$room] = apiRoom();

    Sanctum::actingAs($adminUser, ['*']);

    $this->postJson(route('bonfire.api.rooms.members.store', $room), ['member_id' => $target->id])
        ->assertCreated()
        ->assertJsonPath('data.id', $target->id);

    expect(DB::table('bonfire_member_room')
        ->where('room_id', $room->id)
        ->where('member_id', $target->id)
        ->exists())->toBeTrue();
});

it('removes a member from a room as an admin', function (): void {
    [$adminUser] = apiActor('Boss', BonfireRole::Admin);
    [, $target] = apiActor('Ada');
    [$room] = apiRoom();
    $room->addMember($target);

    Sanctum::actingAs($adminUser, ['*']);

    $this->deleteJson(route('bonfire.api.rooms.members.destroy', [$room, $target]))->assertNoContent();

    expect(DB::table('bonfire_member_room')
        ->where('room_id', $room->id)
        ->where('member_id', $target->id)
        ->exists())->toBeFalse();
});

it('forbids a non-admin from adding a member', function (): void {
    [$plainUser] = apiActor('Regular', BonfireRole::Member);
    [, $target] = apiActor('Ada');
    [$room] = apiRoom();

    Sanctum::actingAs($plainUser, ['*']);

    $this->postJson(route('bonfire.api.rooms.members.store', $room), ['member_id' => $target->id])
        ->assertForbidden();
});

it('forbids a non-admin from removing a member', function (): void {
    [$plainUser] = apiActor('Regular', BonfireRole::Member);
    [, $target] = apiActor('Ada');
    [$room] = apiRoom();
    $room->addMember($target);

    Sanctum::actingAs($plainUser, ['*']);

    $this->deleteJson(route('bonfire.api.rooms.members.destroy', [$room, $target]))->assertForbidden();
});
