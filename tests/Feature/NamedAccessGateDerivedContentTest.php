<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Http\Resources\V1\AttachmentResource;
use ArtisanBuild\Bonfire\Http\Resources\V1\MessageResource;
use ArtisanBuild\Bonfire\Http\Resources\V1\RoomResource;
use ArtisanBuild\Bonfire\Jobs\FetchLinkPreview;
use ArtisanBuild\Bonfire\Models\Attachment;
use ArtisanBuild\Bonfire\Models\LinkPreview;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\SafeUrlGuard;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function derivedGateMember(string $name, BonfireRole $role = BonfireRole::Member): Member
{
    return Bonfire::ensureMember(TestUser::query()->create(['name' => $name]), $name, null, $role);
}

function derivedGateFixture(): array
{
    $admin = derivedGateMember('Admin '.Str::random(5), BonfireRole::Admin);
    $member = derivedGateMember('Ada');
    $room = Room::query()->create([
        'name' => 'Derived '.Str::random(5),
        'slug' => Str::random(10),
        'type' => 0,
        'access_gate' => 'paid',
        'created_by' => $admin->id,
    ]);
    $message = Message::withoutEvents(fn (): Message => Message::query()->create([
        'room_id' => $room->id,
        'member_id' => $member->id,
        'body' => 'https://example.com/secret',
    ]));
    $attachment = Attachment::query()->create([
        'message_id' => $message->id,
        'disk' => 'public',
        'path' => 'bonfire/attachments/secret.txt',
        'filename' => 'secret.txt',
        'mime_type' => 'text/plain',
        'size' => 6,
        'created_at' => now(),
    ]);

    return [$member, $room, $message, $attachment];
}

it('fails closed when API resources are used directly', function (): void {
    [$member, $room, $message, $attachment] = derivedGateFixture();
    Bonfire::gate('paid', fn (Member $resolved): bool => false);
    config()->set('bonfire.resolve_member', fn (): Member => $member);

    expect(fn () => (new RoomResource($room))->toArray(Request::create('/')))
        ->toThrow(AuthorizationException::class);
    expect(fn () => (new MessageResource($message))->toArray(Request::create('/')))
        ->toThrow(AuthorizationException::class);
    expect(fn () => (new AttachmentResource($attachment))->toArray(Request::create('/')))
        ->toThrow(AuthorizationException::class);
});

it('does not fetch or persist link previews after access is denied', function (): void {
    [$member, $room, $message] = derivedGateFixture();
    Bonfire::gate('paid', fn (Member $resolved): bool => false);
    Http::fake();

    $job = new FetchLinkPreview($message->id, 'https://example.com/secret');
    $job->handle(app(SafeUrlGuard::class));
    $job->failed(new RuntimeException('failed'));

    Http::assertNothingSent();
    expect(LinkPreview::query()->where('message_id', $message->id)->exists())->toBeFalse();
});

it('denies attachment policy access when the room gate rejects the member', function (): void {
    [$member, $room, $message, $attachment] = derivedGateFixture();
    Bonfire::gate('paid', fn (Member $resolved): bool => false);

    expect(Gate::forUser($member->memberable)->allows('view', $attachment))->toBeFalse();
});
