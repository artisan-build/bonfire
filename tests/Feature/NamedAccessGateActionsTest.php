<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Actions\AddMemberToRoom;
use ArtisanBuild\Bonfire\Actions\AnnounceMeetingEnd;
use ArtisanBuild\Bonfire\Actions\AnnounceMeetingStart;
use ArtisanBuild\Bonfire\Actions\AssignRoomToSection;
use ArtisanBuild\Bonfire\Actions\CancelScheduledMessage;
use ArtisanBuild\Bonfire\Actions\DeleteChannel;
use ArtisanBuild\Bonfire\Actions\DeleteMessage;
use ArtisanBuild\Bonfire\Actions\EditMessage;
use ArtisanBuild\Bonfire\Actions\EndCall;
use ArtisanBuild\Bonfire\Actions\ForceDeleteRoom;
use ArtisanBuild\Bonfire\Actions\ForwardMessage;
use ArtisanBuild\Bonfire\Actions\InitiateCall;
use ArtisanBuild\Bonfire\Actions\InviteToMeeting;
use ArtisanBuild\Bonfire\Actions\MarkCallActive;
use ArtisanBuild\Bonfire\Actions\MarkRoomRead;
use ArtisanBuild\Bonfire\Actions\OpenDirectMessage;
use ArtisanBuild\Bonfire\Actions\ReleaseDueScheduledMessages;
use ArtisanBuild\Bonfire\Actions\RemoveMemberFromRoom;
use ArtisanBuild\Bonfire\Actions\RestoreChannel;
use ArtisanBuild\Bonfire\Actions\SendMessage;
use ArtisanBuild\Bonfire\Actions\SendScheduledMessageNow;
use ArtisanBuild\Bonfire\Actions\TogglePin;
use ArtisanBuild\Bonfire\Actions\TogglePollVote;
use ArtisanBuild\Bonfire\Actions\ToggleReaction;
use ArtisanBuild\Bonfire\Actions\ToggleRoomStar;
use ArtisanBuild\Bonfire\Actions\UpdateRoom;
use ArtisanBuild\Bonfire\Actions\UpdateScheduledMessage;
use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\CallSession;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\FindOwnedScheduledMessage;
use ArtisanBuild\Bonfire\Support\UnreadTracker;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function actionGateMember(string $name, BonfireRole $role = BonfireRole::Member): Member
{
    return Bonfire::ensureMember(TestUser::query()->create(['name' => $name]), $name, null, $role);
}

function actionGateRoom(?string $gate = 'paid'): Room
{
    $admin = actionGateMember('Admin '.Str::random(5), BonfireRole::Admin);

    return Room::query()->create([
        'name' => 'Room '.Str::random(5),
        'slug' => Str::random(10),
        'type' => 0,
        'access_gate' => $gate,
        'created_by' => $admin->id,
    ]);
}

function actionGateMessage(Room $room, Member $member, array $attributes = []): Message
{
    return Message::withoutEvents(fn (): Message => Message::query()->create(array_merge([
        'room_id' => $room->id,
        'member_id' => $member->id,
        'body' => 'unchanged',
    ], $attributes)));
}

it('blocks direct message read and write actions before they change state', function (): void {
    $member = actionGateMember('Ada');
    $room = actionGateRoom();
    $message = actionGateMessage($room, $member, [
        'poll' => ['question' => 'Choose', 'options' => ['A', 'B']],
    ]);

    Bonfire::gate('paid', fn (Member $resolved): bool => false);

    expect(fn () => Bonfire::postAs($member, $room, 'blocked'))->toThrow(AuthorizationException::class);
    expect(fn () => app(SendMessage::class)(
        $member, $room, 'blocked', null, null, null, [], [], false,
    ))->toThrow(AuthorizationException::class);
    expect(fn () => app(EditMessage::class)($message, 'changed', $member))->toThrow(AuthorizationException::class);
    expect(fn () => app(DeleteMessage::class)($message, $member))->toThrow(AuthorizationException::class);
    expect(fn () => app(TogglePin::class)($member, $message))->toThrow(AuthorizationException::class);
    expect(fn () => app(ToggleReaction::class)($member, $message, '👍'))->toThrow(AuthorizationException::class);
    expect(fn () => app(TogglePollVote::class)($member, $message, 0))->toThrow(AuthorizationException::class);
    expect(fn () => app(ToggleRoomStar::class)($member, $room))->toThrow(AuthorizationException::class);
    expect(fn () => app(MarkRoomRead::class)($room, $member))->toThrow(AuthorizationException::class);
    expect(fn () => app(UnreadTracker::class)->lastReadAt($room, $member))->toThrow(AuthorizationException::class);
    expect(fn () => app(UnreadTracker::class)->hasUnread($room, $member))->toThrow(AuthorizationException::class);

    expect($message->fresh()->body)->toBe('unchanged')
        ->and($message->fresh()->deleted_at)->toBeNull()
        ->and($message->fresh()->pinned_at)->toBeNull()
        ->and(DB::table('bonfire_reactions')->count())->toBe(0)
        ->and(DB::table('bonfire_poll_votes')->count())->toBe(0)
        ->and(DB::table('bonfire_starred_rooms')->count())->toBe(0)
        ->and(DB::table('bonfire_room_reads')->count())->toBe(0);
});

it('checks both the source and destination when forwarding', function (): void {
    $member = actionGateMember('Ada');
    $deniedSource = actionGateRoom();
    $allowedSource = actionGateRoom(null);
    $deniedTarget = actionGateRoom();
    $allowedTarget = actionGateRoom(null);
    $deniedMessage = actionGateMessage($deniedSource, $member);
    $allowedMessage = actionGateMessage($allowedSource, $member);

    Bonfire::gate('paid', fn (Member $resolved): bool => false);

    expect(fn () => app(ForwardMessage::class)($member, $deniedMessage, $allowedTarget))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(ForwardMessage::class)($member, $allowedMessage, $deniedTarget))
        ->toThrow(AuthorizationException::class);

    expect(Message::query()->where('body', 'like', '%data-bonfire-forward%')->exists())->toBeFalse();
});

it('keeps denied scheduled messages hidden and unreleased', function (): void {
    $member = actionGateMember('Ada');
    $room = actionGateRoom();
    $future = actionGateMessage($room, $member, ['scheduled_for' => now()->addDay()]);
    $due = actionGateMessage($room, $member, ['scheduled_for' => now()->subMinute()]);

    Bonfire::gate('paid', fn (Member $resolved): bool => false);

    expect(app(FindOwnedScheduledMessage::class)($member, $future->id))->toBeNull()
        ->and(app(UpdateScheduledMessage::class)($member, $future->id, 'changed', now()->addDays(2)))->toBeNull()
        ->and(app(CancelScheduledMessage::class)($member, $future->id))->toBeNull()
        ->and(app(SendScheduledMessageNow::class)($member, $future->id))->toBeNull();

    $released = app(ReleaseDueScheduledMessages::class)($member, $room);

    expect($released)->toHaveCount(0)
        ->and($future->fresh()->scheduled_for)->not->toBeNull()
        ->and($future->fresh()->body)->toBe('unchanged')
        ->and($due->fresh()->scheduled_for)->not->toBeNull();
});

it('blocks call meeting and invitation entry points', function (): void {
    $caller = actionGateMember('Ada');
    $callee = actionGateMember('Grace');
    $room = actionGateRoom();
    $session = CallSession::query()->create([
        'room_id' => $room->id,
        'caller_member_id' => $caller->id,
        'callee_member_id' => $callee->id,
        'status' => 'ringing',
    ]);

    Bonfire::gate('paid', fn (Member $resolved): bool => false);

    expect(fn () => app(InitiateCall::class)($room, $caller, $callee))->toThrow(AuthorizationException::class);
    expect(fn () => app(InviteToMeeting::class)($room, $caller, $callee))->toThrow(AuthorizationException::class);
    expect(fn () => app(AnnounceMeetingStart::class)($room, $caller))->toThrow(AuthorizationException::class);
    expect(fn () => app(AnnounceMeetingEnd::class)($room, $caller, 2))->toThrow(AuthorizationException::class);
    expect(fn () => app(MarkCallActive::class)($session, $callee))->toThrow(AuthorizationException::class);
    expect(fn () => app(EndCall::class)($session, $caller, 'ended'))->toThrow(AuthorizationException::class);

    expect($session->fresh()->status)->toBe('ringing')
        ->and(Cache::has("bonfire.meeting.{$room->id}.started_at"))->toBeFalse()
        ->and(CallSession::query()->count())->toBe(1)
        ->and(Message::query()->count())->toBe(0);
});

it('blocks room administration and sidebar actions on denied rooms', function (): void {
    $admin = actionGateMember('Ada', BonfireRole::Admin);
    $target = actionGateMember('Grace');
    $room = actionGateRoom();

    Bonfire::gate('paid', fn (Member $resolved): bool => false);

    expect(fn () => app(AddMemberToRoom::class)($room, $target, $admin))->toThrow(AuthorizationException::class);
    expect(fn () => app(RemoveMemberFromRoom::class)($room, $target, $admin))->toThrow(AuthorizationException::class);
    expect(fn () => app(AssignRoomToSection::class)($admin, $room->id, null))->toThrow(AuthorizationException::class);
    expect(fn () => app(UpdateRoom::class)($room, 'name', 'Changed', $admin))->toThrow(AuthorizationException::class);
    expect(fn () => app(DeleteChannel::class)($room, $admin))->toThrow(AuthorizationException::class);
    expect(fn () => app(RestoreChannel::class)($room, $admin))->toThrow(AuthorizationException::class);
    expect(fn () => app(ForceDeleteRoom::class)($room, $admin))->toThrow(AuthorizationException::class);

    $ids = collect([$admin->id, $target->id])->sort()->implode('-');
    $direct = Room::query()->create([
        'name' => 'Direct',
        'slug' => 'dm-'.$ids,
        'type' => RoomType::Private->value,
        'access_gate' => 'paid',
        'created_by' => $admin->id,
    ]);
    $direct->addMember($admin);
    $direct->addMember($target);

    expect(fn () => app(OpenDirectMessage::class)($admin, $target))->toThrow(AuthorizationException::class);

    expect($room->fresh()->name)->not->toBe('Changed')
        ->and($room->fresh()->deleted_at)->toBeNull()
        ->and($room->hasMember($target))->toBeFalse();
});
