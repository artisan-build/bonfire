<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Actions\SendMessage;
use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Attachment;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake('public');

    // The user-footer view links to host-application routes; register stubs so
    // the component renders inside the package test environment.
    Route::get('/profile', fn () => '')->name('profile.edit');
    Route::get('/admin/features', fn () => '')->name('admin.features.index');
    Route::post('/logout', fn () => '')->name('logout');
});

function uvRoom(int $type = 0): Room
{
    $admin = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Admin']), 'Admin', null, BonfireRole::Admin);

    return Room::query()->create([
        'name' => 'Room '.Str::random(6),
        'slug' => Str::random(8),
        'type' => $type,
        'created_by' => $admin->getKey(),
    ]);
}

function uvActingMember(string $name = 'Ada'): Member
{
    $user = TestUser::query()->create(['name' => $name]);
    $member = Bonfire::ensureMember($user, $name);
    auth()->setUser($user);

    return $member;
}

// ── AC1: disallowed-type attachment is rejected, nothing persisted ──────────

it('rejects a message attachment with a disallowed type (php)', function (): void {
    $room = uvRoom();
    uvActingMember();

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('pendingAttachments', [UploadedFile::fake()->create('evil.php', 10, 'text/x-php')])
        ->call('send')
        ->assertHasErrors('pendingAttachments.0');

    expect(Message::query()->count())->toBe(0)
        ->and(Attachment::query()->count())->toBe(0);
});

it('rejects an svg attachment (xss vector excluded from the allowlist)', function (): void {
    $room = uvRoom();
    uvActingMember();

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('pendingAttachments', [UploadedFile::fake()->create('x.svg', 5, 'image/svg+xml')])
        ->call('send')
        ->assertHasErrors('pendingAttachments.0');

    expect(Message::query()->count())->toBe(0)
        ->and(Attachment::query()->count())->toBe(0);
});

// ── AC2: oversized attachment is rejected, nothing persisted ────────────────

it('rejects an oversized attachment above max_attachment_size_kb', function (): void {
    $room = uvRoom();
    uvActingMember();

    $maxKb = (int) config('bonfire.max_attachment_size_kb');

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('pendingAttachments', [UploadedFile::fake()->create('big.png', $maxKb + 1, 'image/png')])
        ->call('send')
        ->assertHasErrors('pendingAttachments.0');

    expect(Message::query()->count())->toBe(0)
        ->and(Attachment::query()->count())->toBe(0);
});

// ── AC8: cloned/existing attachment references are count-bounded ────────────

it('rejects a message referencing more than 10 existing attachment ids', function (): void {
    $room = uvRoom();
    uvActingMember();

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('pendingAttachmentIds', range(1, 11))
        ->call('send')
        ->assertHasErrors('pendingAttachmentIds');

    expect(Message::query()->count())->toBe(0)
        ->and(Attachment::query()->count())->toBe(0);
});

// ── AC3: valid image + pdf within size succeeds ─────────────────────────────

it('accepts valid image and pdf attachments within size and persists them', function (): void {
    $room = uvRoom();
    uvActingMember();

    Livewire::test('bonfire::message-composer', ['room' => $room])
        ->set('pendingAttachments', [
            UploadedFile::fake()->image('photo.png'),
            UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])
        ->call('send')
        ->assertHasNoErrors();

    expect(Message::query()->count())->toBe(1)
        ->and(Attachment::query()->count())->toBe(2);
});

// ── AC4: persisted filename is sanitized; disk path stays hashed ────────────

it('sanitizes a malicious client filename while keeping a hashed disk path', function (): void {
    $room = uvRoom();
    $member = uvActingMember();

    $traversal = UploadedFile::fake()->create('../../../../etc/passwd', 1, 'image/png');
    $quoted = UploadedFile::fake()->create('"evil".png', 1, 'image/png');
    $controls = UploadedFile::fake()->create("a\r\nb\0c.png", 1, 'image/png');
    $long = UploadedFile::fake()->create(str_repeat('a', 400).'.png', 1, 'image/png');

    $message = app(SendMessage::class)(
        $member, $room, '📎', null, null, null,
        [$traversal, $quoted, $controls, $long], [], false,
    );

    $attachments = Attachment::query()->where('message_id', $message->id)->get();

    expect($attachments)->toHaveCount(4);

    foreach ($attachments as $attachment) {
        expect($attachment->filename)
            ->not->toContain('/')
            ->not->toContain('\\')
            ->not->toMatch('/[\x00-\x1F\x7F]/');

        expect(mb_strlen((string) $attachment->filename))->toBeLessThanOrEqual(255);

        // The disk path is the hashed store() result, never the raw name.
        expect($attachment->path)
            ->toStartWith('bonfire/attachments/')
            ->not->toBe($attachment->filename);

        Storage::disk('public')->assertExists($attachment->path);
    }

    $names = $attachments->pluck('filename')->all();

    expect($names)->toContain('passwd')   // traversal stripped to basename
        ->toContain('"evil".png')         // quotes/space preserved, harmless
        ->toContain('abc.png');           // control chars removed
});

// ── AC5: avatar rejects svg + non-image, accepts a real raster image ────────

it('rejects an svg avatar', function (): void {
    uvActingMember();

    Livewire::test('bonfire::user-footer')
        ->set('profileDisplayName', 'Ada')
        ->set('profileAvatar', UploadedFile::fake()->create('x.svg', 2, 'image/svg+xml'))
        ->call('saveProfile')
        ->assertHasErrors('profileAvatar');
});

it('rejects a non-image avatar (html)', function (): void {
    uvActingMember();

    Livewire::test('bonfire::user-footer')
        ->set('profileDisplayName', 'Ada')
        ->set('profileAvatar', UploadedFile::fake()->create('x.html', 2, 'text/html'))
        ->call('saveProfile')
        ->assertHasErrors('profileAvatar');
});

it('accepts a valid raster avatar and updates the avatar url', function (): void {
    $member = uvActingMember();

    Livewire::test('bonfire::user-footer')
        ->set('profileDisplayName', 'Ada')
        ->set('profileAvatar', UploadedFile::fake()->image('a.png'))
        ->call('saveProfile')
        ->assertHasNoErrors();

    $member->refresh();

    expect($member->avatar_url)->not->toBeNull()
        ->and($member->avatar_url)->toContain('/storage/avatars/');
});
