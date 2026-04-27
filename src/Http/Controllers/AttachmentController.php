<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Http\Controllers;

use ArtisanBuild\Bonfire\Models\Attachment;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves Bonfire attachments with per-room access control.
 */
class AttachmentController extends Controller
{
    public function show(Attachment $attachment): StreamedResponse|Response
    {
        $attachment->loadMissing('message.room');
        $message = $attachment->message;
        $room = $message?->getRelationValue('room');

        if (! $room instanceof Room) {
            abort(404);
        }

        $resolver = config('bonfire.resolve_member');
        $member = is_callable($resolver) ? $resolver() : null;

        if (! $room->isPrivate()) {
            if ($member !== null && ! $member->is_active) {
                abort(403);
            }
        } else {
            if ($member === null || ! $member->is_active || ! $room->hasMember($member)) {
                abort(403);
            }
        }

        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->path)) {
            abort(404);
        }

        return $disk->response($attachment->path, $attachment->filename, [
            'Content-Type' => $attachment->mime_type,
        ]);
    }
}
