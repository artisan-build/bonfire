<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the optional forward-poll cursor for the message index.
 * Authorization is handled by the controller via RoomPolicy@view.
 */
class ListMessagesRequest extends FormRequest
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
            'after' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
