<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a message edit. Authorization is handled by the controller via
 * MessagePolicy@update.
 */
class EditMessageRequest extends FormRequest
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
            'body' => ['required', 'string', 'min:1', 'max:10000'],
        ];
    }
}
