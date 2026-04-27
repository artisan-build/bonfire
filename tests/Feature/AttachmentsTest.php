<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Attachment;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
});

function anAttachmentInRoom(int $roomType = 0): array
{
    $room = aRoom($roomType);
    $author = Bonfire::ensureMember(TestUser::query()->create(['name' => 'Ada']), 'Ada');
    $message = Bonfire::postAs($author, $room, 'see file');

    $file = UploadedFile::fake()->create('notes.txt', 2, 'text/plain');
    $path = $file->storeAs('bonfire/0/'.$room->id, 'notes.txt', 'public');

    $attachment = Attachment::query()->create([
        'message_id' => $message->id,
        'disk' => 'public',
        'path' => $path,
        'filename' => 'notes.txt',
        'mime_type' => 'text/plain',
        'size' => 2,
        'created_at' => now(),
    ]);

    return [$room, $attachment, $author];
}

it('serves attachments from public rooms to anyone', function (): void {
    [, $attachment, $author] = anAttachmentInRoom();

    $this->actingAs($author->memberable)
        ->get(route('bonfire.attachments.show', $attachment))
        ->assertOk();
});

it('denies attachments in private rooms to non-members', function (): void {
    [$room, $attachment] = anAttachmentInRoom(RoomType::Private->value);

    $stranger = TestUser::query()->create(['name' => 'Stranger']);
    Bonfire::ensureMember($stranger, 'Stranger');
    auth()->setUser($stranger);

    $this->get(route('bonfire.attachments.show', $attachment))
        ->assertForbidden();
});

it('serves private-room attachments to pivot members', function (): void {
    [$room, $attachment] = anAttachmentInRoom(RoomType::Private->value);

    $user = TestUser::query()->create(['name' => 'Ada']);
    $member = Bonfire::memberFor($user) ?? Bonfire::ensureMember($user, 'Ada');
    $room->addMember($member);
    auth()->setUser($user);

    $this->get(route('bonfire.attachments.show', $attachment))
        ->assertOk();
});

it('returns 404 when the stored file is missing', function (): void {
    [, $attachment, $author] = anAttachmentInRoom();

    Storage::disk('public')->delete($attachment->path);

    $this->actingAs($author->memberable)
        ->get(route('bonfire.attachments.show', $attachment))
        ->assertNotFound();
});
