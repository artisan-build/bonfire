<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property int $room_id
 * @property int $caller_member_id
 * @property int $callee_member_id
 * @property string $status
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CallSession extends Model
{
    protected $table = 'bonfire_call_sessions';

    protected $guarded = [];

    /**
     * The room this call session is associated with.
     *
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    /**
     * The member who initiated the call.
     *
     * @return BelongsTo<Member, $this>
     */
    public function caller(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'caller_member_id');
    }

    /**
     * The member who received the call.
     *
     * @return BelongsTo<Member, $this>
     */
    public function callee(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'callee_member_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['ringing', 'active'], true);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
