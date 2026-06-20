<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the member id to add to a room. Authorization is handled by the
 * controller via RoomPolicy@manage (the documented roster-edit gate).
 */
class AddRoomMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'exists:bonfire_members,id'],
        ];
    }
}
