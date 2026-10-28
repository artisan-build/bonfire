<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Facades;

use ArtisanBuild\Bonfire\BonfireManager;
use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Member ensureMember(Model $memberable, string $displayName, ?string $avatarUrl = null, BonfireRole $role = BonfireRole::Member)
 * @method static void gate(string $name, callable(Member): bool $resolver)
 * @method static bool allowsGate(string $name, Member $member)
 * @method static Member|null memberFor(?Model $model)
 * @method static Message postAs(Member $member, Room $room, string $body, ?Message $parent = null)
 * @method static Member promote(Member $member, BonfireRole $role)
 * @method static Member deactivate(Member $member)
 * @method static Member reactivate(Member $member)
 * @method static string userChannelName(int|string $id)
 */
class Bonfire extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BonfireManager::class;
    }
}
