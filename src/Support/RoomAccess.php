<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Support;

use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Auth\Access\AuthorizationException;
use Throwable;

final class RoomAccess
{
    public static function currentMember(): ?Member
    {
        $resolver = config('bonfire.resolve_member');

        if (! is_callable($resolver)) {
            return null;
        }

        try {
            $member = $resolver();
        } catch (Throwable) {
            return null;
        }

        return $member instanceof Member ? $member : null;
    }

    public static function allows(Room $room, ?Member $member, bool $allowAnonymousPublic = false): bool
    {
        if ($member !== null) {
            return $room->isAccessibleBy($member);
        }

        return $allowAnonymousPublic && ! $room->isPrivate() && $room->access_gate === null;
    }

    /** @throws AuthorizationException */
    public static function authorize(Room $room, ?Member $member, bool $allowAnonymousPublic = false): void
    {
        if (! self::allows($room, $member, $allowAnonymousPublic)) {
            throw new AuthorizationException('This room is not accessible.');
        }
    }

    /**
     * Preserve legacy action behavior for ungated rooms while ensuring actorless
     * direct calls cannot write to a gated room.
     *
     * @throws AuthorizationException
     */
    public static function authorizeGate(Room $room, ?Member $member = null): void
    {
        if ($room->access_gate === null) {
            return;
        }

        self::authorize($room, $member ?? self::currentMember());
    }
}
