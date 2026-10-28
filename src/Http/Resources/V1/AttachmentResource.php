<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Http\Resources\V1;

use ArtisanBuild\Bonfire\Http\Resources\V1\Concerns\ResolvesCurrentMember;
use ArtisanBuild\Bonfire\Models\Attachment;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\RoomAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * Projection of a message attachment. The `url` points at the package's
 * access-controlled attachment route rather than the raw disk path.
 *
 * @mixin Attachment
 */
class AttachmentResource extends JsonResource
{
    use ResolvesCurrentMember;

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('message.room');
        $room = $this->resource->message?->getRelationValue('room');

        if (! $room instanceof Room) {
            throw new AuthorizationException('This attachment has no accessible room.');
        }

        $roomKey = 'bonfire_authorized_room_'.$room->getKey();

        if ($request->attributes->get($roomKey) !== true) {
            RoomAccess::authorizeGate($room, $this->currentMember($request));
            $request->attributes->set($roomKey, true);
        }

        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'url' => route('bonfire.attachments.show', $this->resource),
        ];
    }
}
