<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Attachment;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;

/**
 * Persist a composed message and its attachments.
 *
 * Encapsulates the three creation paths (immediate post via the manager,
 * scheduled post created without firing MessagePosted, and poll post), the
 * storing/cloning of attachments, and the optional channel copy that
 * accompanies a reply. The Livewire component is left to gather inputs,
 * authorize, and update its own state.
 *
 * Collaborators are resolved at call-time via the Bonfire facade so a host
 * application's bound manager replacement is honoured.
 */
final class SendMessage
{
    /**
     * @param  array<string, mixed>|null  $poll
     * @param  array<int, UploadedFile>  $uploads
     * @param  array<int, int>  $existingAttachmentIds
     */
    public function __invoke(
        Member $member,
        Room $room,
        string $body,
        ?Message $parent,
        ?CarbonInterface $scheduledAt,
        ?array $poll,
        array $uploads,
        array $existingAttachmentIds,
        bool $alsoSendToChannel,
    ): Message {
        $message = $this->createMessage($member, $room, $body, $parent, $scheduledAt, $poll);

        $this->storeAttachments($member, $message, $uploads, $existingAttachmentIds);

        if ($parent !== null && $alsoSendToChannel && $scheduledAt === null) {
            Bonfire::postAs($member, $room, $body, null);
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>|null  $poll
     */
    private function createMessage(Member $member, Room $room, string $body, ?Message $parent, ?CarbonInterface $scheduledAt, ?array $poll): Message
    {
        if ($scheduledAt === null && $poll === null) {
            return Bonfire::postAs($member, $room, $body, $parent);
        }

        $attrs = [
            'room_id' => $room->getKey(),
            'member_id' => $member->getKey(),
            'parent_id' => $parent?->getKey(),
            'body' => $body,
            'poll' => $poll,
            'scheduled_for' => $scheduledAt,
        ];

        if ($scheduledAt !== null) {
            return Message::withoutEvents(fn (): Message => Message::query()->create($attrs));
        }

        return Message::query()->create($attrs);
    }

    /**
     * @param  array<int, UploadedFile>  $uploads
     * @param  array<int, int>  $existingAttachmentIds
     */
    private function storeAttachments(Member $member, Message $message, array $uploads, array $existingAttachmentIds): void
    {
        if ($uploads === [] && $existingAttachmentIds === []) {
            return;
        }

        $disk = (string) config('bonfire.disk', 'public');

        foreach ($uploads as $upload) {
            $path = $upload->store('bonfire/attachments', $disk);

            if ($path === false) {
                continue;
            }

            Attachment::query()->create([
                'message_id' => $message->id,
                'disk' => $disk,
                'path' => $path,
                'filename' => $this->sanitizeFilename($upload->getClientOriginalName()),
                'mime_type' => $upload->getMimeType() ?? 'application/octet-stream',
                'size' => $upload->getSize() ?? 0,
                'created_at' => now(),
            ]);
        }

        // Clone selected recent attachments by referencing the same stored path,
        // but only those owned by the posting member.
        if ($existingAttachmentIds !== []) {
            $existing = Attachment::query()
                ->whereIn('id', $existingAttachmentIds)
                ->whereHas('message', fn ($q) => $q->where('member_id', $member->id))
                ->get();

            foreach ($existing as $src) {
                Attachment::query()->create([
                    'message_id' => $message->id,
                    'disk' => $src->disk,
                    'path' => $src->path,
                    // Source rows were sanitized on their original write; re-run
                    // for defence in depth and consistency.
                    'filename' => $this->sanitizeFilename((string) $src->filename),
                    'mime_type' => $src->mime_type,
                    'size' => $src->size,
                    'created_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reduce a client-supplied filename to a safe display value for the
     * `filename` column (which feeds Content-Disposition on download): strip
     * any path components, remove control characters and directory separators,
     * collapse whitespace, and cap the length. The stored disk path is the
     * hashed `store()` result and is never derived from this value.
     */
    private function sanitizeFilename(string $name): string
    {
        // Drop any directory components a client may have supplied (both
        // separators), guarding against path traversal like "../../etc/passwd".
        $name = basename(str_replace('\\', '/', $name));

        // Remove control characters (incl. NUL, CR, LF — header-injection
        // surface) and any residual slashes.
        $name = (string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/', '', $name);

        // Collapse internal whitespace runs and trim the edges.
        $name = trim((string) preg_replace('/\s+/', ' ', $name));

        if ($name === '') {
            return 'file';
        }

        if (mb_strlen($name) > 255) {
            $name = rtrim(mb_substr($name, 0, 255));
        }

        return $name;
    }
}
