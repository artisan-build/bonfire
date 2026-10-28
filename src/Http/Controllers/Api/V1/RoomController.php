<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Http\Controllers\Api\V1;

use ArtisanBuild\Bonfire\Actions\MarkRoomRead;
use ArtisanBuild\Bonfire\Http\Resources\V1\RoomResource;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class RoomController extends Controller
{
    /**
     * Rooms the acting member belongs to. Membership is the implicit view grant,
     * so no per-room authorization is needed here.
     */
    public function index(): AnonymousResourceCollection
    {
        $member = $this->actingMember();

        // Mirror the active-member guard the policies enforce on every other
        // endpoint: a deactivated member must not enumerate their rooms.
        abort_if($member === null || ! $member->is_active, 403);

        $rooms = $member->rooms()
            ->orderBy('bonfire_rooms.name')
            ->get()
            ->filter(fn (Room $room): bool => $room->isAccessibleBy($member))
            ->values();

        return RoomResource::collection($rooms);
    }

    public function show(Room $room): RoomResource
    {
        $this->authorize('view', $room);

        return new RoomResource($room);
    }

    public function read(Room $room, MarkRoomRead $markRoomRead): Response
    {
        $this->authorize('view', $room);

        $member = $this->actingMember();
        abort_if($member === null, 403);

        $markRoomRead($room, $member);

        return response()->noContent();
    }
}
