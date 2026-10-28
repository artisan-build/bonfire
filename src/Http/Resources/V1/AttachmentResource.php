<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Http\Resources\V1;

use ArtisanBuild\Bonfire\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * Projection of a message attachment. The `url` points at the package's
 * access-controlled attachment route rather than the raw disk path.
 *
 * @mixin Attachment
 */
class AttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'url' => route('bonfire.attachments.show', $this->resource),
        ];
    }
}
