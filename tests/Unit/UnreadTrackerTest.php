<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\UnreadTracker;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

it('returns a CarbonInterface from lastReadAt when the app uses CarbonImmutable', function (): void {
    Date::use(CarbonImmutable::class);

    $room = new Room(['id' => 1, 'type' => 0]);
    $room->exists = true;
    $room->setKeyName('id');
    $room->forceFill(['id' => 1]);

    $tracker = new UnreadTracker;
    $tracker->markRead($room, null);

    $result = $tracker->lastReadAt($room, null);

    expect($result)->toBeInstanceOf(CarbonInterface::class);
});
