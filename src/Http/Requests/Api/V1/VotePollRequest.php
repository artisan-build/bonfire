<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a poll vote. The option index is range-checked against the poll's
 * options in the controller. Authorization is handled via MessagePolicy@vote.
 */
class VotePollRequest extends FormRequest
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
            'option_index' => ['required', 'integer', 'min:0'],
        ];
    }
}
