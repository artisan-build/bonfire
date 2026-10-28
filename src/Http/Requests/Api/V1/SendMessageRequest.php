<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a posted message. Bounds mirror the Blade composer (body max 10k,
 * poll question max 500, 2–10 options each max 200). Authorization is handled
 * by the controller via RoomPolicy@post.
 */
class SendMessageRequest extends FormRequest
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
            'body' => ['required_without:poll', 'nullable', 'string', 'max:10000'],
            'parent_id' => ['nullable', 'integer'],
            'poll' => ['nullable', 'array'],
            'poll.question' => ['required_with:poll', 'string', 'min:1', 'max:500'],
            'poll.options' => ['required_with:poll', 'array', 'min:2', 'max:10'],
            'poll.options.*' => ['required', 'string', 'min:1', 'max:200'],
        ];
    }
}
