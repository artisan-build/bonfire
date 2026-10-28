<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Http\Controllers\Api\V1;

use ArtisanBuild\Bonfire\Actions\AddMemberToRoom;
use ArtisanBuild\Bonfire\Actions\RemoveMemberFromRoom;
use ArtisanBuild\Bonfire\Http\Requests\Api\V1\AddRoomMemberRequest;
use ArtisanBuild\Bonfire\Http\Resources\V1\MemberResource;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Roster management. Both endpoints gate on RoomPolicy@manage — the documented
 * admin-only roster-edit ability, mirroring the Blade admin panel which
 * authorizes `manage` against Room::class for the same operations.
 */
class RoomMemberController extends Controller
{
    public function store(AddRoomMemberRequest $request, Room $room, AddMemberToRoom $addMemberToRoom): JsonResponse
    {
        $this->authorize('manage', Room::class);

        if ($room->access_gate !== null) {
            $this->authorize('view', $room);
        }

        $target = Member::query()->findOrFail($request->integer('member_id'));
        $actor = $this->actingMember();

        $addMemberToRoom($room, $target, $actor);

        return (new MemberResource($target))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Room $room, Member $member, RemoveMemberFromRoom $removeMemberFromRoom): Response
    {
        $this->authorize('manage', Room::class);

        if ($room->access_gate !== null) {
            $this->authorize('view', $room);
        }

        $actor = $this->actingMember();
        abort_if($actor === null, 403);

        $removeMemberFromRoom($room, $member, $actor);

        return response()->noContent();
    }
}
