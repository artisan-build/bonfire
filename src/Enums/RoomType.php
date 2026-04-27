<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Enums;

enum RoomType: int
{
    case Private = 1;

    case Archived = 2;

    case Announcements = 4;

    public static function has(int $bitmask, self $flag): bool
    {
        return ($bitmask & $flag->value) === $flag->value;
    }

    public static function add(int $bitmask, self $flag): int
    {
        return $bitmask | $flag->value;
    }

    public static function remove(int $bitmask, self $flag): int
    {
        return $bitmask & ~$flag->value;
    }
}
