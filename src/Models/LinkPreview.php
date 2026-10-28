<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * Stores link preview metadata for a message.
 */
class LinkPreview extends Model
{
    public $timestamps = false;

    protected $table = 'bonfire_link_previews';

    /** @var list<string> */
    protected $fillable = [
        'message_id',
        'url',
        'title',
        'description',
        'image_url',
        'site_name',
        'fetched_at',
        'failed',
    ];

    /**
     * The message this link preview belongs to.
     *
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'failed' => 'boolean',
            'fetched_at' => 'datetime',
        ];
    }
}
