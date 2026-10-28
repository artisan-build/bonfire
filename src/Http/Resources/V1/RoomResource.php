<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Http\Resources\V1;

use ArtisanBuild\Bonfire\Http\Resources\V1\Concerns\ResolvesCurrentMember;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\RoomAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * The stable v1 contract for a room. Type is exposed as explicit boolean flags
 * derived from the bitmask rather than leaking the raw `type` integer.
 *
 * @mixin Room
 */
class RoomResource extends JsonResource
{
    use ResolvesCurrentMember;

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        RoomAccess::authorizeGate($this->resource, $this->currentMember($request));
        $request->attributes->set('bonfire_authorized_room_'.$this->getKey(), true);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_private' => $this->isPrivate(),
            'is_archived' => $this->isArchived(),
            'is_announcement' => $this->isAnnouncements(),
            'created_at' => $this->created_at->toISOString(),
            // Present only when the room was loaded through the member's
            // membership pivot (e.g. the rooms index), so it is free to include.
            'last_read_at' => $this->whenPivotLoaded(
                'bonfire_member_room',
                fn () => $this->resource->getAttribute('pivot')?->getAttribute('last_read_at'),
            ),
        ];
    }
}
