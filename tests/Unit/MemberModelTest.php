<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;

it('resolves polymorphic memberable', function (): void {
    $user = TestUser::query()->create(['name' => 'Ada']);

    $member = Member::query()->create([
        'memberable_type' => $user->getMorphClass(),
        'memberable_id' => $user->getKey(),
        'display_name' => 'Ada',
        'role' => BonfireRole::Member->value,
        'is_active' => true,
    ]);

    expect($member->memberable)->toBeInstanceOf(TestUser::class)
        ->and($member->memberable->getKey())->toBe($user->getKey());
});

it('casts role to the enum', function (): void {
    $user = TestUser::query()->create(['name' => 'Ada']);

    $member = Member::query()->create([
        'memberable_type' => $user->getMorphClass(),
        'memberable_id' => $user->getKey(),
        'display_name' => 'Ada',
        'role' => BonfireRole::Admin->value,
        'is_active' => true,
    ]);

    expect($member->fresh()->role)->toBe(BonfireRole::Admin);
});

it('filters with the active scope', function (): void {
    $user = TestUser::query()->create(['name' => 'Active']);
    $other = TestUser::query()->create(['name' => 'Dormant']);

    Member::query()->create([
        'memberable_type' => $user->getMorphClass(),
        'memberable_id' => $user->getKey(),
        'display_name' => 'Active',
        'role' => BonfireRole::Member->value,
        'is_active' => true,
    ]);
    Member::query()->create([
        'memberable_type' => $other->getMorphClass(),
        'memberable_id' => $other->getKey(),
        'display_name' => 'Dormant',
        'role' => BonfireRole::Member->value,
        'is_active' => false,
    ]);

    expect(Member::query()->active()->count())->toBe(1);
});

it('filters with the tenant scope', function (): void {
    $user = TestUser::query()->create(['name' => 'One']);
    $two = TestUser::query()->create(['name' => 'Two']);

    Member::query()->create([
        'memberable_type' => $user->getMorphClass(),
        'memberable_id' => $user->getKey(),
        'tenant_id' => 5,
        'display_name' => 'One',
        'role' => BonfireRole::Member->value,
        'is_active' => true,
    ]);
    Member::query()->create([
        'memberable_type' => $two->getMorphClass(),
        'memberable_id' => $two->getKey(),
        'tenant_id' => null,
        'display_name' => 'Two',
        'role' => BonfireRole::Member->value,
        'is_active' => true,
    ]);

    expect(Member::query()->forTenant(5)->count())->toBe(1)
        ->and(Member::query()->forTenant(null)->count())->toBe(1);
});
