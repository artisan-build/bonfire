<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;

it('admin has at least every role', function (): void {
    expect(BonfireRole::Admin->hasAtLeast(BonfireRole::Admin))->toBeTrue()
        ->and(BonfireRole::Admin->hasAtLeast(BonfireRole::Moderator))->toBeTrue()
        ->and(BonfireRole::Admin->hasAtLeast(BonfireRole::Member))->toBeTrue();
});

it('moderator has at least moderator and member', function (): void {
    expect(BonfireRole::Moderator->hasAtLeast(BonfireRole::Admin))->toBeFalse()
        ->and(BonfireRole::Moderator->hasAtLeast(BonfireRole::Moderator))->toBeTrue()
        ->and(BonfireRole::Moderator->hasAtLeast(BonfireRole::Member))->toBeTrue();
});

it('member has at least member only', function (): void {
    expect(BonfireRole::Member->hasAtLeast(BonfireRole::Admin))->toBeFalse()
        ->and(BonfireRole::Member->hasAtLeast(BonfireRole::Moderator))->toBeFalse()
        ->and(BonfireRole::Member->hasAtLeast(BonfireRole::Member))->toBeTrue();
});
