<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire;

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\AccessGateEvaluator;
use ArtisanBuild\Bonfire\Support\AccessGateRegistry;
use ArtisanBuild\Bonfire\Support\MemberBadgeRegistry;
use ArtisanBuild\Bonfire\Support\MemberBadgeResolver;
use ArtisanBuild\Bonfire\Support\RoomAccess;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class BonfireManager
{
    public function __construct(
        private readonly AccessGateRegistry $accessGates,
        private readonly AccessGateEvaluator $accessGateEvaluator,
        private readonly MemberBadgeRegistry $memberBadges,
        private readonly MemberBadgeResolver $memberBadgeResolver,
    ) {}

    /**
     * @param  callable(Member): mixed  $resolver
     */
    public function gate(string $name, callable $resolver): void
    {
        $this->accessGates->register($name, $resolver);
    }

    public function allowsGate(string $name, Member $member): bool
    {
        return $this->accessGateEvaluator->allows($name, $member);
    }

    /**
     * @param  callable(Member): mixed  $resolver
     */
    public function resolveBadgesUsing(callable $resolver): void
    {
        $this->memberBadges->register($resolver);
    }

    /**
     * @return list<array{label: string, url: string, title: string|null}>
     */
    public function badgesFor(Member $member): array
    {
        return $this->memberBadgeResolver->resolve($member);
    }

    public function ensureMember(
        Model $memberable,
        string $displayName,
        ?string $avatarUrl = null,
        BonfireRole $role = BonfireRole::Member,
    ): Member {
        $member = Member::query()->updateOrCreate(
            [
                'memberable_type' => $memberable->getMorphClass(),
                'memberable_id' => $memberable->getKey(),
            ],
            [
                'display_name' => $displayName,
                'avatar_url' => $avatarUrl,
            ],
        );

        if ($member->wasRecentlyCreated) {
            $member->role = $role;
            $member->is_active = true;
            $member->save();
        }

        return $member;
    }

    public function memberFor(?Model $model): ?Member
    {
        if (! $model instanceof Model) {
            return null;
        }

        return Member::query()
            ->where('memberable_type', $model->getMorphClass())
            ->where('memberable_id', $model->getKey())
            ->first();
    }

    public function postAs(
        Member $member,
        Room $room,
        string $body,
        ?Message $parent = null,
    ): Message {
        RoomAccess::authorizeGate($room, $member);

        $trimmed = trim($body);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Message body cannot be empty.');
        }

        if ($parent !== null && $parent->isReply()) {
            throw new InvalidArgumentException('Cannot reply to a reply.');
        }

        return Message::query()->create([
            'room_id' => $room->getKey(),
            'member_id' => $member->getKey(),
            'parent_id' => $parent?->getKey(),
            'body' => $trimmed,
        ]);
    }

    public function userChannelName(int|string $id): string
    {
        $formatter = config('bonfire.user_channel');

        if (! is_callable($formatter)) {
            return 'App.Models.User.'.$id;
        }

        return (string) $formatter($id);
    }

    public function promote(Member $member, BonfireRole $role): Member
    {
        $member->role = $role;
        $member->save();

        return $member;
    }

    public function deactivate(Member $member): Member
    {
        $member->is_active = false;
        $member->save();

        return $member;
    }

    public function reactivate(Member $member): Member
    {
        $member->is_active = true;
        $member->save();

        return $member;
    }
}
