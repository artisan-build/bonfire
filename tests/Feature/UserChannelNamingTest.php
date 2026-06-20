<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Events\CallEnded;
use ArtisanBuild\Bonfire\Events\CallInitiated;
use ArtisanBuild\Bonfire\Events\CallSignal;
use ArtisanBuild\Bonfire\Events\MemberMentioned;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\CallSession;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Builds one instance of each of the three call events that broadcast to the
 * host user channel, all targeting the same user id.
 *
 * @return array<string, ShouldBroadcast>
 */
function callEventsTargeting(int $targetUserId): array
{
    $room = aRoom();

    $session = CallSession::query()->create([
        'room_id' => $room->id,
        'caller_member_id' => $room->created_by,
        'callee_member_id' => $room->created_by,
        'status' => 'ringing',
    ]);

    return [
        'CallSignal' => new CallSignal($session->id, $targetUserId, 'offer', []),
        'CallInitiated' => new CallInitiated($session, 'Caller', null, $targetUserId),
        'CallEnded' => new CallEnded($session->id, $targetUserId),
    ];
}

it('names all call event channels with the default App.Models.User formatter', function (): void {
    foreach (callEventsTargeting(123) as $name => $event) {
        $channels = $event->broadcastOn();

        expect($channels[0]->name)
            ->toBe('private-App.Models.User.123', "{$name} should target the default host channel");
    }
});

it('routes all call event channels through the configured user_channel formatter', function (): void {
    config(['bonfire.user_channel' => fn ($id) => 'tenant.'.$id]);

    foreach (callEventsTargeting(123) as $name => $event) {
        $channels = $event->broadcastOn();

        expect($channels[0]->name)
            ->toBe('private-tenant.123', "{$name} should honor the overridden formatter");
    }
});

it('routes MemberMentioned through the user_channel helper', function (): void {
    $author = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Ada']), 'Ada');
    $target = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Grace']), 'Grace');
    $room = aRoom();
    // Unsaved message — broadcastOn() only reads the mentioned member's id, and
    // persisting a Message would fire mention notifications (no notifications
    // table in the package test database).
    $message = new Message([
        'room_id' => $room->id,
        'member_id' => $author->id,
        'body' => 'Hey @Grace',
    ]);

    $event = new MemberMentioned($target, $author, $message, $room);

    expect($event->broadcastOn()[0]->name)
        ->toBe('private-App.Models.User.'.$target->memberable_id);

    config(['bonfire.user_channel' => fn ($id) => 'tenant.'.$id]);

    expect($event->broadcastOn()[0]->name)
        ->toBe('private-tenant.'.$target->memberable_id);
});
