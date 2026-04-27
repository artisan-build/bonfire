<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Mention;
use ArtisanBuild\Bonfire\Notifications\MentionedInMessage;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestBot;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
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
