<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('bonfire.link_preview_enabled', false);
    app('router')->get('bonfire/{room:slug}', fn () => '')->name('bonfire.room.show');
});

function aForwardSource(): array
{
    $sourceRoom = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    $member = Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);
    $source = Bonfire::postAs($member, $sourceRoom, 'hello world');

    return [$source, $member, $sourceRoom];
}

it('forwards a message to another public channel with attribution', function (): void {
    [$source, $member, $sourceRoom] = aForwardSource();

    $target = Room::query()->create([
        'name' => 'Target '.Str::random(6),
        'slug' => Str::random(8),
        'type' => 0,
        'created_by' => $member->id,
    ]);

    Livewire::test('bonfire::message-list', ['room' => $sourceRoom])
        ->call('forwardMessage', $source->id, $target->id, 'FYI check this');

    $forwarded = Message::query()
        ->where('room_id', $target->id)
        ->latest()
        ->first();

    expect($forwarded)->not->toBeNull()
        ->and($forwarded->body)->toContain('FYI check this')
        ->and($forwarded->body)->toContain('Ada')
        ->and($forwarded->body)->toContain('hello world')
        ->and($forwarded->body)->toContain('data-bonfire-forward="'.$source->id.'"');
});

it('rejects forwarding to a private room the user is not in', function (): void {
    [$source, $member, $sourceRoom] = aForwardSource();

    $otherAdmin = TestUser::query()->create(['name' => 'Bob']);
    $otherMember = Bonfire::ensureMember($otherAdmin, 'Bob');

    $privateTarget = Room::query()->create([
        'name' => 'Private '.Str::random(6),
        'slug' => Str::random(8),
        'type' => 1, // RoomType::Private
        'created_by' => $otherMember->id,
    ]);

    Livewire::test('bonfire::message-list', ['room' => $sourceRoom])
        ->call('forwardMessage', $source->id, $privateTarget->id, '')
        ->assertStatus(403);

    expect(Message::query()->where('room_id', $privateTarget->id)->count())->toBe(0);
});

it('truncates quoted body to 300 chars', function (): void {
    $sourceRoom = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    $member = Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);
    $long = str_repeat('x', 500);
    $source = Bonfire::postAs($member, $sourceRoom, $long);

    $target = Room::query()->create([
        'name' => 'Target '.Str::random(6),
        'slug' => Str::random(8),
        'type' => 0,
        'created_by' => $member->id,
    ]);

    Livewire::test('bonfire::message-list', ['room' => $sourceRoom])
        ->call('forwardMessage', $source->id, $target->id, '');

    $forwarded = Message::query()->where('room_id', $target->id)->first();

    // 300-char truncation + attribution line ≈ ~350 max
    expect(mb_strlen(strip_tags($forwarded->body)))->toBeLessThan(400);
});
