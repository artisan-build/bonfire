<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Actions\AssignRoomToSection;
use ArtisanBuild\Bonfire\Actions\CreateChannel;
use ArtisanBuild\Bonfire\Actions\CreateChannelSection;
use ArtisanBuild\Bonfire\Actions\DeleteChannel;
use ArtisanBuild\Bonfire\Actions\DeleteChannelSection;
use ArtisanBuild\Bonfire\Actions\OpenDirectMessage;
use ArtisanBuild\Bonfire\Actions\RenameChannelSection;
use ArtisanBuild\Bonfire\Actions\RestoreChannel;
use ArtisanBuild\Bonfire\Actions\ToggleRoomStar;
use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\ChannelSection;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function raMember(string $name): Member
{
    return Bonfire::ensureMember(TestUser::query()->create(['name' => $name]), $name);
}

function raRoom(int $type = 0, ?string $slug = null): Room
{
    $admin = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Admin']), 'Admin', null, BonfireRole::Admin);

    return Room::query()->create([
        'name' => 'Room '.Str::random(6),
        'slug' => $slug ?? Str::random(10),
        'type' => $type,
        'created_by' => $admin->getKey(),
    ]);
}

// --- CreateChannelSection ---

it('creates a section at max position + 1 for the right member, name trimmed and limited to 80', function (): void {
    $member = raMember('Ada');
    $other = raMember('Bob');

    // Pre-seed the member with a section at position 5 and an unrelated member's section.
    ChannelSection::query()->create(['member_id' => $member->id, 'name' => 'First', 'position' => 5]);
    ChannelSection::query()->create(['member_id' => $other->id, 'name' => 'Other', 'position' => 99]);

    $long = str_repeat('x', 200);
    $section = app(CreateChannelSection::class)($member, '  '.$long.'  ');

    expect($section)->not->toBeNull()
        ->and($section->member_id)->toBe($member->id)
        ->and($section->position)->toBe(6)
        ->and(mb_strlen($section->name))->toBe(80)
        ->and($section->name)->toBe(str_repeat('x', 80));
});

it('treats a blank section name as a no-op', function (): void {
    $member = raMember('Ada');

    expect(app(CreateChannelSection::class)($member, '   '))->toBeNull()
        ->and(ChannelSection::query()->count())->toBe(0);
});

// --- RenameChannelSection ---

it('renames only the members own section', function (): void {
    $member = raMember('Ada');
    $other = raMember('Bob');

    $mine = ChannelSection::query()->create(['member_id' => $member->id, 'name' => 'Mine', 'position' => 1]);
    $theirs = ChannelSection::query()->create(['member_id' => $other->id, 'name' => 'Theirs', 'position' => 1]);

    app(RenameChannelSection::class)($member, $mine->id, '  Renamed  ');
    app(RenameChannelSection::class)($member, $theirs->id, 'Hacked');

    expect($mine->refresh()->name)->toBe('Renamed')
        ->and($theirs->refresh()->name)->toBe('Theirs');
});

it('treats a blank rename as a no-op', function (): void {
    $member = raMember('Ada');
    $section = ChannelSection::query()->create(['member_id' => $member->id, 'name' => 'Keep', 'position' => 1]);

    app(RenameChannelSection::class)($member, $section->id, '   ');

    expect($section->refresh()->name)->toBe('Keep');
});

// --- DeleteChannelSection ---

it('deletes the members own section and nulls its pivot section_id', function (): void {
    $member = raMember('Ada');
    $room = raRoom();

    $section = ChannelSection::query()->create(['member_id' => $member->id, 'name' => 'Misc', 'position' => 1]);

    DB::table('bonfire_member_room')->insert([
        'member_id' => $member->id,
        'room_id' => $room->id,
        'section_id' => $section->id,
        'created_by' => $member->id,
        'created_at' => now(),
    ]);

    app(DeleteChannelSection::class)($member, $section->id);

    expect(ChannelSection::query()->whereKey($section->id)->exists())->toBeFalse()
        ->and(DB::table('bonfire_member_room')
            ->where('member_id', $member->id)
            ->where('room_id', $room->id)
            ->value('section_id'))->toBeNull();
});

it('does not delete another members section', function (): void {
    $member = raMember('Ada');
    $other = raMember('Bob');
    $theirs = ChannelSection::query()->create(['member_id' => $other->id, 'name' => 'Theirs', 'position' => 1]);

    app(DeleteChannelSection::class)($member, $theirs->id);

    expect(ChannelSection::query()->whereKey($theirs->id)->exists())->toBeTrue();
});

// --- AssignRoomToSection ---

it('inserts a pivot with the section when none exists', function (): void {
    $member = raMember('Ada');
    $room = raRoom();
    $section = ChannelSection::query()->create(['member_id' => $member->id, 'name' => 'S', 'position' => 1]);

    app(AssignRoomToSection::class)($member, $room->id, $section->id);

    expect(DB::table('bonfire_member_room')
        ->where('member_id', $member->id)
        ->where('room_id', $room->id)
        ->value('section_id'))->toBe($section->id);
});

it('updates the section when a pivot already exists', function (): void {
    $member = raMember('Ada');
    $room = raRoom();
    $room->addMember($member);
    $section = ChannelSection::query()->create(['member_id' => $member->id, 'name' => 'S', 'position' => 1]);

    app(AssignRoomToSection::class)($member, $room->id, $section->id);
    expect(DB::table('bonfire_member_room')
        ->where('member_id', $member->id)->where('room_id', $room->id)
        ->value('section_id'))->toBe($section->id);

    app(AssignRoomToSection::class)($member, $room->id, null);
    expect(DB::table('bonfire_member_room')
        ->where('member_id', $member->id)->where('room_id', $room->id)
        ->value('section_id'))->toBeNull();
});

it('is a no-op when assigning a foreign section to an existing pivot', function (): void {
    $member = raMember('Ada');
    $other = raMember('Bob');
    $room = raRoom();
    $room->addMember($member);

    $valid = ChannelSection::query()->create(['member_id' => $member->id, 'name' => 'Mine', 'position' => 1]);
    $foreign = ChannelSection::query()->create(['member_id' => $other->id, 'name' => 'Theirs', 'position' => 1]);

    app(AssignRoomToSection::class)($member, $room->id, $valid->id);
    app(AssignRoomToSection::class)($member, $room->id, $foreign->id);

    expect(DB::table('bonfire_member_room')
        ->where('member_id', $member->id)->where('room_id', $room->id)
        ->value('section_id'))->toBe($valid->id);
});

// --- OpenDirectMessage ---

it('creates a dm room with the sorted-id slug and both members on first open', function (): void {
    $current = raMember('Ada');
    $target = raMember('Bob');

    $room = app(OpenDirectMessage::class)($current, $target);

    $expectedSlug = 'dm-'.collect([$current->id, $target->id])->sort()->implode('-');

    expect($room->slug)->toBe($expectedSlug)
        ->and($room->isPrivate())->toBeTrue()
        ->and($room->hasMember($current))->toBeTrue()
        ->and($room->hasMember($target))->toBeTrue();
});

it('reuses the same dm room when called again with reversed args', function (): void {
    $current = raMember('Ada');
    $target = raMember('Bob');

    $first = app(OpenDirectMessage::class)($current, $target);
    $second = app(OpenDirectMessage::class)($target, $current);

    expect($second->id)->toBe($first->id)
        ->and(Room::query()->where('slug', 'like', 'dm-%')->count())->toBe(1);
});

// --- CreateChannel ---

it('builds the correct bitmask and adds the creator plus selected members', function (): void {
    $creator = raMember('Ada');
    $alice = raMember('Alice');
    $bob = raMember('Bob');

    $room = app(CreateChannel::class)(
        $creator,
        'Project X',
        '  a description  ',
        true,
        true,
        [$alice->id, $bob->id, $creator->id], // creator id is skipped
    );

    expect($room->type)->toBe(RoomType::Private->value | RoomType::Announcements->value)
        ->and($room->type)->toBe(5)
        ->and($room->description)->toBe('a description')
        ->and($room->hasMember($creator))->toBeTrue()
        ->and($room->hasMember($alice))->toBeTrue()
        ->and($room->hasMember($bob))->toBeTrue()
        ->and(DB::table('bonfire_member_room')->where('room_id', $room->id)->count())->toBe(3);
});

it('resolves slug collisions to base, base-2, base-3 matching the naive loop', function (): void {
    $creator = raMember('Ada');

    // base is free -> base
    $first = app(CreateChannel::class)($creator, 'General', null, false, false, []);
    expect($first->slug)->toBe('general');

    // base taken -> base-2
    $second = app(CreateChannel::class)($creator, 'General', null, false, false, []);
    expect($second->slug)->toBe('general-2');

    // base and base-2 taken -> base-3
    $third = app(CreateChannel::class)($creator, 'General', null, false, false, []);
    expect($third->slug)->toBe('general-3');
});

it('resolves the slug with a single query regardless of collision count', function (): void {
    $creator = raMember('Ada');

    // Seed three colliding rooms so the naive loop would need 4 existence checks.
    raRoom(slug: 'general');
    raRoom(slug: 'general-2');
    raRoom(slug: 'general-3');

    $selects = [];
    DB::listen(function ($query) use (&$selects): void {
        if (str_starts_with(strtolower(ltrim($query->sql)), 'select')
            && str_contains(strtolower($query->sql), 'slug')) {
            $selects[] = $query->sql;
        }
    });

    $room = app(CreateChannel::class)($creator, 'General', null, false, false, []);

    expect($room->slug)->toBe('general-4')
        ->and($selects)->toHaveCount(1);
});

// --- DeleteChannel / RestoreChannel ---

it('soft-deletes a channel', function (): void {
    $room = raRoom();

    app(DeleteChannel::class)($room);

    expect(Room::query()->whereKey($room->id)->exists())->toBeFalse()
        ->and(Room::withTrashed()->whereKey($room->id)->first()->trashed())->toBeTrue();
});

it('restores a trashed channel and is a no-op for a live one', function (): void {
    $room = raRoom();
    $room->delete();

    app(RestoreChannel::class)($room);
    expect($room->refresh()->trashed())->toBeFalse();

    // Restoring an already-live room changes nothing.
    app(RestoreChannel::class)($room);
    expect($room->refresh()->trashed())->toBeFalse();
});

// --- toggleStar delegates to the shared ToggleRoomStar ---

it('stars then unstars a room via the shared ToggleRoomStar action', function (): void {
    $member = raMember('Ada');
    $room = raRoom();

    $starred = fn (): bool => DB::table('bonfire_starred_rooms')
        ->where('member_id', $member->id)->where('room_id', $room->id)->exists();

    expect(app(ToggleRoomStar::class)($member, $room))->toBeTrue()
        ->and($starred())->toBeTrue();

    expect(app(ToggleRoomStar::class)($member, $room))->toBeFalse()
        ->and($starred())->toBeFalse();
});
