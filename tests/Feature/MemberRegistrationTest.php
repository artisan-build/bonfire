<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Observers\BonfireMemberObserver;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestBot;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;

it('creates a new member for a host model', function (): void {
    $user = TestUser::query()->create(['name' => 'Ada']);

    $member = Bonfire::ensureMember($user, 'Ada Lovelace', 'https://example.com/a.png');

    expect($member)->toBeInstanceOf(Member::class)
        ->and($member->display_name)->toBe('Ada Lovelace')
        ->and($member->avatar_url)->toBe('https://example.com/a.png')
        ->and($member->role)->toBe(BonfireRole::Member)
        ->and($member->is_active)->toBeTrue()
        ->and(Member::query()->count())->toBe(1);
});

it('updates the existing member on subsequent calls', function (): void {
    $user = TestUser::query()->create(['name' => 'Ada']);

    Bonfire::ensureMember($user, 'Ada', null);
    Bonfire::ensureMember($user, 'Ada Lovelace', 'https://example.com/new.png');

    expect(Member::query()->count())->toBe(1);

    $member = Bonfire::memberFor($user);
    expect($member->display_name)->toBe('Ada Lovelace')
        ->and($member->avatar_url)->toBe('https://example.com/new.png');
});

it('does not overwrite the role on subsequent calls', function (): void {
    $user = TestUser::query()->create(['name' => 'Ada']);

    Bonfire::ensureMember($user, 'Ada', null, BonfireRole::Admin);
    Bonfire::ensureMember($user, 'Ada', null, BonfireRole::Member);

    expect(Bonfire::memberFor($user)->role)->toBe(BonfireRole::Admin);
});

it('assigns the requested role on creation', function (): void {
    $user = TestUser::query()->create(['name' => 'Ada']);

    $member = Bonfire::ensureMember($user, 'Ada', null, BonfireRole::Moderator);

    expect($member->role)->toBe(BonfireRole::Moderator);
});

it('resolves members via polymorphic lookup across model types', function (): void {
    $user = TestUser::query()->create(['name' => 'Ada']);
    $bot = TestBot::query()->create(['name' => 'Helpdesk']);

    Bonfire::ensureMember($user, 'Ada');
    Bonfire::ensureMember($bot, 'Helpdesk');

    expect(Bonfire::memberFor($user)->memberable_type)->toBe($user->getMorphClass())
        ->and(Bonfire::memberFor($bot)->memberable_type)->toBe($bot->getMorphClass())
        ->and(Bonfire::memberFor($user)->getKey())->not->toBe(Bonfire::memberFor($bot)->getKey());
});

it('returns null when no member exists for the host model', function (): void {
    $user = TestUser::query()->create(['name' => 'Nobody']);

    expect(Bonfire::memberFor($user))->toBeNull()
        ->and(Bonfire::memberFor(null))->toBeNull();
});

it('scopes members by configured tenant id', function (): void {
    $user = TestUser::query()->create(['name' => 'Ada']);

    config()->set('bonfire.tenant_id', fn () => 1);
    Bonfire::ensureMember($user, 'Ada Tenant 1');

    config()->set('bonfire.tenant_id', fn () => 2);
    Bonfire::ensureMember($user, 'Ada Tenant 2');

    expect(Member::query()->count())->toBe(2);

    config()->set('bonfire.tenant_id', fn () => 1);
    expect(Bonfire::memberFor($user)->display_name)->toBe('Ada Tenant 1');

    config()->set('bonfire.tenant_id', fn () => 2);
    expect(Bonfire::memberFor($user)->display_name)->toBe('Ada Tenant 2');
});

it('syncs the member via the observer', function (): void {
    TestUser::observe(BonfireMemberObserver::class);

    $user = TestUser::query()->create(['name' => 'Ada']);

    expect(Bonfire::memberFor($user))->not->toBeNull()
        ->and(Bonfire::memberFor($user)->display_name)->toBe('Ada');

    $user->update(['name' => 'Ada Lovelace']);

    expect(Bonfire::memberFor($user)->display_name)->toBe('Ada Lovelace');
});

it('promotes, deactivates, and reactivates members', function (): void {
    $user = TestUser::query()->create(['name' => 'Ada']);
    $member = Bonfire::ensureMember($user, 'Ada');

    Bonfire::promote($member, BonfireRole::Admin);
    expect($member->fresh()->role)->toBe(BonfireRole::Admin);

    Bonfire::deactivate($member);
    expect($member->fresh()->is_active)->toBeFalse();

    Bonfire::reactivate($member);
    expect($member->fresh()->is_active)->toBeTrue();
});
