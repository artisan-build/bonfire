<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire;

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class BonfireManager
{
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
                'tenant_id' => $this->tenantId(),
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
            ->where('tenant_id', $this->tenantId())
            ->first();
    }

    public function postAs(
        Member $member,
        Room $room,
        string $body,
        ?Message $parent = null,
    ): Message {
        $trimmed = trim($body);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Message body cannot be empty.');
        }

        if ($parent !== null && $parent->isReply()) {
            throw new InvalidArgumentException('Cannot reply to a reply.');
        }

        return Message::query()->create([
            'tenant_id' => $this->tenantId(),
            'room_id' => $room->getKey(),
            'member_id' => $member->getKey(),
            'parent_id' => $parent?->getKey(),
            'body' => $trimmed,
        ]);
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

    public function tenantId(): ?int
    {
        $resolver = config('bonfire.tenant_id');

        if (is_callable($resolver)) {
            $value = $resolver();

            return $value === null ? null : (int) $value;
        }

        return $resolver === null ? null : (int) $resolver;
    }
}
