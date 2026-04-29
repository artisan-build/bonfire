<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * Represents an emoji reaction on a message. Composite key (message_id,
 * member_id, emoji) lets a single member react with multiple emoji.
 */
class Reaction extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'bonfire_reactions';

    protected $primaryKey = null;

    protected $guarded = [];

    protected $fillable = ['message_id', 'member_id', 'emoji', 'created_at'];

    /**
     * The message this reaction belongs to.
     *
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    /**
     * The member who reacted.
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
