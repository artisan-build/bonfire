<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Reaction;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Database\QueryException;

function aMessage(): array
{
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    $member = Bonfire::ensureMember($user, 'Ada');
    $message = Bonfire::postAs($member, $room, 'hello');

    return [$message, $member];
}

it('enforces one reaction per member per message', function (): void {
    [$message, $member] = aMessage();

    Reaction::query()->create([
        'message_id' => $message->id,
        'member_id' => $member->id,
        'created_at' => now(),
    ]);

    $duplicate = fn () => Reaction::query()->create([
        'message_id' => $message->id,
        'member_id' => $member->id,
        'created_at' => now(),
    ]);

    expect($duplicate)->toThrow(QueryException::class);
});

it('tracks reactions via the message relation', function (): void {
    [$message, $member] = aMessage();

    Reaction::query()->create([
        'message_id' => $message->id,
        'member_id' => $member->id,
        'created_at' => now(),
    ]);

    expect($message->reactions()->count())->toBe(1);
});

it('does not count reactions on deleted messages', function (): void {
    [$message, $member] = aMessage();

    Reaction::query()->create([
        'message_id' => $message->id,
        'member_id' => $member->id,
        'created_at' => now(),
    ]);

    $message->delete();

    expect($message->fresh()->trashed())->toBeTrue();
});
