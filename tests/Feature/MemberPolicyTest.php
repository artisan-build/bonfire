<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;

function memberPolicyMember(string $name, BonfireRole $role = BonfireRole::Member): Member
{
    $user = TestUser::query()->create(['name' => $name]);

    return Bonfire::ensureMember($user, $name, null, $role);
}

it('reserves member administration for admins', function (): void {
    $admin = memberPolicyMember('Admin', BonfireRole::Admin);
    $mod = memberPolicyMember('Mod', BonfireRole::Moderator);
    $member = memberPolicyMember('Ada');

    expect(Gate::forUser($admin->memberable)->allows('administer', Member::class))->toBeTrue()
        ->and(Gate::forUser($mod->memberable)->allows('administer', Member::class))->toBeFalse()
        ->and(Gate::forUser($member->memberable)->allows('administer', Member::class))->toBeFalse();
});

it('only lets admins change roles and toggle activation', function (): void {
    $admin = memberPolicyMember('Admin', BonfireRole::Admin);
    $member = memberPolicyMember('Ada');
    $target = memberPolicyMember('Bob');

    expect(Gate::forUser($admin->memberable)->allows('changeRole', $target))->toBeTrue()
        ->and(Gate::forUser($member->memberable)->allows('changeRole', $target))->toBeFalse()
        ->and(Gate::forUser($admin->memberable)->allows('toggleActive', $target))->toBeTrue()
        ->and(Gate::forUser($member->memberable)->allows('toggleActive', $target))->toBeFalse();
});

it('lets an admin kick others but not themselves', function (): void {
    $admin = memberPolicyMember('Admin', BonfireRole::Admin);
    $target = memberPolicyMember('Bob');

    expect(Gate::forUser($admin->memberable)->allows('kick', $target))->toBeTrue()
        ->and(Gate::forUser($admin->memberable)->allows('kick', $admin))->toBeFalse();
});

it('forbids an inactive admin from administering', function (): void {
    $admin = memberPolicyMember('Admin', BonfireRole::Admin);
    Bonfire::deactivate($admin);
    $admin->refresh();

    expect(Gate::forUser($admin->memberable)->allows('administer', Member::class))->toBeFalse();
});
