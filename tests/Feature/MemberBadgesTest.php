<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\BonfireManager;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Http\Resources\V1\MemberResource;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;

it('returns no badges without a registered resolver', function (): void {
    [, $member] = apiActor('Ada');

    expect($member->badges())->toBe([]);

    $resource = (new MemberResource($member))->toArray(Request::create('/'));

    expect($resource)->toHaveKeys(['id', 'display_name', 'avatar_url', 'role', 'badges'])
        ->and(array_keys($resource))->toBe(['id', 'display_name', 'avatar_url', 'role', 'badges'])
        ->and($resource['badges'])->toBe([])
        ->and($resource)->not->toHaveKey('memberable_type')
        ->and($resource)->not->toHaveKey('memberable_id');
});

it('normalizes one or many badges in resolver order with an optional title', function (): void {
    [, $member] = apiActor('Ada');

    Bonfire::resolveBadgesUsing(fn (Member $resolved): array => []);

    expect($member->badges())->toBe([]);

    Bonfire::resolveBadgesUsing(fn (Member $resolved): array => [
        ['label' => 'Helper', 'url' => 'https://partner.test/ada'],
    ]);

    expect($member->badges())->toBe([
        ['label' => 'Helper', 'url' => 'https://partner.test/ada', 'title' => null],
    ]);

    Bonfire::resolveBadgesUsing(fn (Member $resolved): array => [
        ['label' => 'Second', 'url' => '/second', 'title' => 'Second badge'],
        ['label' => 'First', 'url' => '/first'],
    ]);

    $badges = $member->badges();

    expect($badges)->toBe([
        ['label' => 'Second', 'url' => '/second', 'title' => 'Second badge'],
        ['label' => 'First', 'url' => '/first', 'title' => null],
    ])->and((new MemberResource($member))->toArray(Request::create('/'))['badges'])->toBe($badges);
});

it('escapes every host-supplied badge string', function (): void {
    [, $member] = apiActor('Ada');

    Bonfire::resolveBadgesUsing(fn (Member $resolved): array => [[
        'label' => 'Help "<script>alert(\'x\')</script>',
        'url' => 'https://partner.test/?next="><img onerror=\'x\'>',
        'title' => 'Open "Ada\'s" <profile>',
    ]]);

    expect($member->badges())->toBe([[
        'label' => 'Help &quot;&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;',
        'url' => 'https://partner.test/?next=&quot;&gt;&lt;img onerror=&#039;x&#039;&gt;',
        'title' => 'Open &quot;Ada&#039;s&quot; &lt;profile&gt;',
    ]]);
});

it('skips malformed entries and returns empty when the resolver fails', function (): void {
    [, $member] = apiActor('Ada');

    Bonfire::resolveBadgesUsing(fn (Member $resolved): array => [
        'not an entry',
        ['label' => 'missing url'],
        ['label' => 123, 'url' => '/number'],
        ['label' => 'bad title', 'url' => '/bad-title', 'title' => []],
        ['label' => 'Valid', 'url' => '/valid', 'extra' => 'ignored'],
    ]);

    expect($member->badges())->toBe([
        ['label' => 'Valid', 'url' => '/valid', 'title' => null],
    ]);

    Bonfire::resolveBadgesUsing(function (Member $resolved): array {
        throw new RuntimeException('resolver failed');
    });

    expect($member->badges())->toBe([])
        ->and(fn (): array => (new MemberResource($member))->toArray(Request::create('/')))
        ->not->toThrow(RuntimeException::class);

    Bonfire::resolveBadgesUsing(function (Member $resolved): Generator {
        yield ['label' => 'Discarded', 'url' => '/discarded'];

        throw new RuntimeException('iteration failed');
    });

    expect($member->badges())->toBe([]);
});

it('keeps resolver registration when scoped services are replaced', function (): void {
    [, $member] = apiActor('Ada');
    $first = app(BonfireManager::class);

    $first->resolveBadgesUsing(fn (Member $resolved): array => [
        ['label' => $resolved->display_name, 'url' => '/members/'.$resolved->getKey()],
    ]);

    app()->forgetScopedInstances();
    $second = app(BonfireManager::class);

    expect($second)->not->toBe($first)
        ->and($second->badgesFor($member))->toBe([[
            'label' => 'Ada',
            'url' => '/members/'.$member->getKey(),
            'title' => null,
        ]]);
});

it('includes badges in nested message members without leaking host fields', function (): void {
    [$user, $member] = apiActor('Ada');
    [$room] = apiRoom();
    $room->addMember($member);
    Message::query()->create([
        'room_id' => $room->getKey(),
        'member_id' => $member->getKey(),
        'body' => 'hello',
    ]);

    Bonfire::resolveBadgesUsing(fn (Member $resolved): array => [[
        'label' => 'I Can Help',
        'url' => 'https://partner.test/members/'.$resolved->getKey(),
        'title' => 'Partner profile',
    ]]);
    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('bonfire.api.rooms.messages.index', $room));

    $response->assertOk()
        ->assertJsonPath('data.0.member.badges.0.label', 'I Can Help')
        ->assertJsonPath('data.0.member.badges.0.url', 'https://partner.test/members/'.$member->getKey())
        ->assertJsonPath('data.0.member.badges.0.title', 'Partner profile');

    expect(array_keys($response->json('data.0.member')))
        ->toBe(['id', 'display_name', 'avatar_url', 'role', 'badges']);
});
