<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;

it('lets members reply to a root message', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    $member = Bonfire::ensureMember($user, 'Ada');

    $root = Bonfire::postAs($member, $room, 'parent');
    $reply = Bonfire::postAs($member, $room, 'child', $root);

    expect($reply->parent_id)->toBe($root->id)
        ->and($root->replies()->count())->toBe(1);
});

it('keeps replies in chronological order', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    $member = Bonfire::ensureMember($user, 'Ada');

    $root = Bonfire::postAs($member, $room, 'parent');
    Bonfire::postAs($member, $room, 'first', $root);
    Bonfire::postAs($member, $room, 'second', $root);
    Bonfire::postAs($member, $room, 'third', $root);

    $bodies = $root->replies()->oldest()->pluck('body')->all();

    expect($bodies)->toBe(['first', 'second', 'third']);
});

it('scopes root messages via the Roots scope', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    $member = Bonfire::ensureMember($user, 'Ada');

    $root = Bonfire::postAs($member, $room, 'parent');
    Bonfire::postAs($member, $room, 'reply', $root);

    expect(Message::query()->roots()->count())->toBe(1);
});
