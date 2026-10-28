<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an emoji reaction. The 16-char cap mirrors the Blade message list so
 * a malformed payload cannot overflow the column. Authorization is handled by
 * the controller via MessagePolicy@react.
 */
class ReactRequest extends FormRequest
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
            'emoji' => ['required', 'string', 'min:1', 'max:16'],
        ];
    }
}
