<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Models;

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Override;

/**
 * Represents a workspace member in Bonfire.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $memberable_type
 * @property int $memberable_id
 * @property string $display_name
 * @property string|null $avatar_url
 * @property BonfireRole $role
 * @property bool $is_active
 * @property bool $is_away
 * @property string|null $phone
 * @property string|null $timezone
 * @property string|null $status_emoji
 * @property string|null $status_text
 * @property Carbon|null $status_expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Member extends Model
{
    use Notifiable;

    protected $table = 'bonfire_members';

    protected $guarded = [];

    /**
     * The polymorphic host model (user, bot, etc.) that this member represents.
     *
     * @return MorphTo<Model, $this>
     */
    public function memberable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The rooms this member belongs to.
     *
     * @return BelongsToMany<Room, $this>
     */
    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'bonfire_member_room', 'member_id', 'room_id')
            ->withPivot(['created_by', 'last_read_at', 'created_at']);
    }

    /**
     * Messages authored by this member.
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'member_id');
    }

    public function hasRoleAtLeast(BonfireRole $role): bool
    {
        return $this->role->hasAtLeast($role);
    }

    public function getAvatarUrlAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Rewrite any stale absolute URLs pointing at localhost/127.0.0.1 to
        // relative paths so the browser uses the current origin.
        return preg_replace('#^https?://(localhost|127\.0\.0\.1)(:\d+)?/#', '/', $value);
    }

    /**
     * Scope query to only active members.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope query to a specific tenant.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function scopeForTenant(Builder $query, ?int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_away' => 'boolean',
            'status_expires_at' => 'datetime',
            'role' => BonfireRole::class,
            'tenant_id' => 'integer',
        ];
    }
}
