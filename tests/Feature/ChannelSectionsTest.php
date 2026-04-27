<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\ChannelSection;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    app('router')->get('bonfire/{room:slug}', fn () => '')->name('bonfire.room.show');
    app('router')->get('/profile', fn () => '')->name('profile.edit');
    app('router')->post('/logout', fn () => '')->name('logout');
});

it('creates a channel section for the current member', function (): void {
    $user = TestUser::query()->create(['name' => 'Ada']);
    $member = Bonfire::ensureMember($user, 'Ada');
    auth()->setUser($user);

    Livewire::test('bonfire::rooms')
        ->call('createSection', 'Team');

    expect(ChannelSection::query()->where('member_id', $member->id)->count())->toBe(1)
        ->and(ChannelSection::query()->first()->name)->toBe('Team');
});

it('assigns a channel to a section and unassigns it', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    $member = Bonfire::ensureMember($user, 'Ada');
    $room->addMember($member);
    auth()->setUser($user);

    $component = Livewire::test('bonfire::rooms')
        ->call('createSection', 'Projects');

    $sectionId = ChannelSection::query()->where('member_id', $member->id)->value('id');

    $component->call('assignRoomToSection', $room->id, $sectionId);

    expect(DB::table('bonfire_member_room')
        ->where('member_id', $member->id)
        ->where('room_id', $room->id)
        ->value('section_id'))->toBe((int) $sectionId);

    $component->call('assignRoomToSection', $room->id, null);

    expect(DB::table('bonfire_member_room')
        ->where('member_id', $member->id)
        ->where('room_id', $room->id)
        ->value('section_id'))->toBeNull();
});

it('deleting a section unassigns its channels', function (): void {
    $room = aRoom();
    $user = TestUser::query()->create(['name' => 'Ada']);
    $member = Bonfire::ensureMember($user, 'Ada');
    $room->addMember($member);
    auth()->setUser($user);

    $component = Livewire::test('bonfire::rooms')
        ->call('createSection', 'Misc');
    $sectionId = ChannelSection::query()->where('member_id', $member->id)->value('id');

    $component->call('assignRoomToSection', $room->id, $sectionId);
    $component->call('deleteSection', $sectionId);

    expect(ChannelSection::query()->count())->toBe(0)
        ->and(DB::table('bonfire_member_room')
            ->where('member_id', $member->id)
            ->where('room_id', $room->id)
            ->value('section_id'))->toBeNull();
});

it('does not show other members sections', function (): void {
    $userA = TestUser::query()->create(['name' => 'Ada']);
    $memberA = Bonfire::ensureMember($userA, 'Ada');
    auth()->setUser($userA);

    Livewire::test('bonfire::rooms')->call('createSection', 'A-only');

    $userB = TestUser::query()->create(['name' => 'Bob']);
    Bonfire::ensureMember($userB, 'Bob');
    auth()->setUser($userB);

    $component = Livewire::test('bonfire::rooms');
    $html = $component->html();

    expect($html)->not->toContain('A-only');
});
