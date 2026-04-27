<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * Represents an at-mention of a member in a message.
 */
class Mention extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'bonfire_mentions';

    protected $primaryKey = null;

    protected $guarded = [];

    /**
     * The message this mention appears in.
     *
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    /**
     * The member who was mentioned.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
