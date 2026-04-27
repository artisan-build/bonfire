<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\RoomType;

it('detects a flag in a bitmask', function (): void {
    expect(RoomType::has(1, RoomType::Private))->toBeTrue();
    expect(RoomType::has(0, RoomType::Private))->toBeFalse();
    expect(RoomType::has(2, RoomType::Archived))->toBeTrue();
    expect(RoomType::has(4, RoomType::Announcements))->toBeTrue();
});

it('adds a flag to a bitmask', function (): void {
    expect(RoomType::add(0, RoomType::Private))->toBe(1);
    expect(RoomType::add(1, RoomType::Announcements))->toBe(5);
    expect(RoomType::add(5, RoomType::Private))->toBe(5);
});

it('removes a flag from a bitmask', function (): void {
    expect(RoomType::remove(5, RoomType::Private))->toBe(4);
    expect(RoomType::remove(1, RoomType::Private))->toBe(0);
    expect(RoomType::remove(0, RoomType::Private))->toBe(0);
});

it('composes multiple flags', function (): void {
    $bitmask = RoomType::add(RoomType::add(0, RoomType::Private), RoomType::Announcements);

    expect($bitmask)->toBe(5)
        ->and(RoomType::has($bitmask, RoomType::Private))->toBeTrue()
        ->and(RoomType::has($bitmask, RoomType::Announcements))->toBeTrue()
        ->and(RoomType::has($bitmask, RoomType::Archived))->toBeFalse();
});
