<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Enums;

enum BonfireRole: string
{
    case Member = 'member';
    case Moderator = 'moderator';
    case Admin = 'admin';

    public function hasAtLeast(self $role): bool
    {
        return match ($this) {
            self::Admin => true,
            self::Moderator => $role !== self::Admin,
            self::Member => $role === self::Member,
        };
    }
}
