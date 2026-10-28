<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Events\MemberMentioned;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Mention;
use ArtisanBuild\Bonfire\Notifications\MentionedInMessage;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestBot;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

it('persists mentions and notifies the mentioned member', function (): void {
    Notification::fake();

    $room = aRoom();
    $author = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Ada']), 'Ada');
    $target = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Grace']), 'Grace');

    Bonfire::postAs($author, $room, 'Hey @Grace please review');

    expect(Mention::query()->where('member_id', $target->id)->count())->toBe(1);

    Notification::assertSentTo($target, MentionedInMessage::class);
});

it('does not create a mention when the author targets themselves', function (): void {
    Notification::fake();

    $room = aRoom();
    $author = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Ada']), 'Ada');

    Bonfire::postAs($author, $room, 'note to self @Ada remember this');

    expect(Mention::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('lets bots mention humans', function (): void {
    Notification::fake();

    $room = aRoom();
    $bot = Bonfire::ensureMember(TestBot::query()->create(['name' => 'Helpdesk']), 'Helpdesk');
    $target = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Grace']), 'Grace');

    Bonfire::postAs($bot, $room, '@Grace your ticket is resolved');

    expect(Mention::query()->where('member_id', $target->id)->count())->toBe(1);
    Notification::assertSentTo($target, MentionedInMessage::class);
});

it('enables bulk mentions by default', function (): void {
    expect(config('bonfire.bulk_mentions'))->toBeTrue();
});

it('fans out bulk mentions when enabled', function (string $token): void {
    Notification::fake();
    Event::fake([MemberMentioned::class]);

    config()->set('bonfire.bulk_mentions', true);

    $room = aRoom();
    $author = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Ada']), 'Ada');
    $target = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Grace']), 'Grace');
    $bystander = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Linus']), 'Linus');
    $room->addMember($target);
    $room->addMember($bystander);

    Bonfire::postAs($author, $room, 'Hello @Grace and '.$token);

    expect(Mention::query()->where('member_id', $target->id)->exists())->toBeTrue();
    expect(Mention::query()->where('member_id', $bystander->id)->exists())->toBeTrue();
    Notification::assertSentTo($target, MentionedInMessage::class);
    Notification::assertSentTo($bystander, MentionedInMessage::class);
    Event::assertDispatched(MemberMentioned::class, fn (MemberMentioned $event): bool => $event->mentioned->is($target));
    Event::assertDispatched(MemberMentioned::class, fn (MemberMentioned $event): bool => $event->mentioned->is($bystander));
})->with([
    'channel case variant' => '@CHANNEL',
    'here case variant' => '@Here',
    'everyone' => '@everyone',
]);

it('leaves bulk mentions inert when disabled', function (string $token): void {
    Notification::fake();
    Event::fake([MemberMentioned::class]);

    config()->set('bonfire.bulk_mentions', false);

    $room = aRoom();
    $author = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Ada']), 'Ada');
    $target = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Grace']), 'Grace');
    $room->addMember($target);

    Bonfire::postAs($author, $room, 'Hello '.$token);

    expect(Mention::query()->count())->toBe(0);
    Notification::assertNothingSent();
    Event::assertNotDispatched(MemberMentioned::class);
})->with([
    'channel case variant' => '@CHANNEL',
    'here case variant' => '@Here',
    'everyone' => '@everyone',
]);

it('keeps explicit mentions active in mixed input when bulk mentions are disabled', function (): void {
    Notification::fake();
    Event::fake([MemberMentioned::class]);

    config()->set('bonfire.bulk_mentions', false);

    $room = aRoom();
    $author = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Ada']), 'Ada');
    $target = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Grace']), 'Grace');
    $bystander = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Linus']), 'Linus');
    $room->addMember($target);
    $room->addMember($bystander);

    Bonfire::postAs($author, $room, 'Hello @Grace and @CHANNEL');

    expect(Mention::query()->pluck('member_id')->all())->toBe([$target->id]);
    Notification::assertSentTo($target, MentionedInMessage::class);
    Notification::assertNotSentTo($bystander, MentionedInMessage::class);
    Event::assertDispatched(MemberMentioned::class, fn (MemberMentioned $event): bool => $event->mentioned->is($target));
    Event::assertNotDispatched(MemberMentioned::class, fn (MemberMentioned $event): bool => $event->mentioned->is($bystander));
});
