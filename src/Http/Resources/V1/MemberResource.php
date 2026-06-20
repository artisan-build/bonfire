<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Http\Resources\V1;

use ArtisanBuild\Bonfire\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * Public, minimal projection of a Bonfire member.
 *
 * Deliberately omits host-coupling and private columns (memberable_type/id,
 * phone, status_*) — only what a chat client needs to render an author.
 *
 * @mixin Member
 */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'avatar_url' => $this->avatar_url,
            'role' => $this->role->value,
        ];
    }
}
