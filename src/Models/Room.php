<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Models;

use ArtisanBuild\Bonfire\Enums\RoomType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Override;

/**
 * Represents a chat room or channel in Bonfire.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $type
 * @property int $created_by
 * @property bool $has_unread
 * @property bool $is_starred
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Room extends Model
{
    use SoftDeletes;

    protected $table = 'bonfire_rooms';

    protected $guarded = [];

    #[Override]
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The member who created this room.
     *
     * @return BelongsTo<Member, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'created_by');
    }

    /**
     * Members belonging to this room.
     *
     * @return BelongsToMany<Member, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'bonfire_member_room', 'room_id', 'member_id')
            ->withPivot(['created_by', 'last_read_at', 'created_at', 'section_id']);
    }

    /**
     * All messages in this room.
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'room_id');
    }

    /**
     * Root-level messages (not replies) in this room.
     *
     * @return HasMany<Message, $this>
     */
    public function rootMessages(): HasMany
    {
        return $this->messages()->whereNull('parent_id');
    }

    public function isPrivate(): bool
    {
        return RoomType::has($this->type, RoomType::Private);
    }

    public function isArchived(): bool
    {
        return RoomType::has($this->type, RoomType::Archived);
    }

    public function isAnnouncements(): bool
    {
        return RoomType::has($this->type, RoomType::Announcements);
    }

    public function hasMember(Member $member): bool
    {
        return $this->members()->whereKey($member->getKey())->exists();
    }

    public function addMember(Member $member, ?Member $createdBy = null): self
    {
        if ($this->hasMember($member)) {
            return $this;
        }

        $this->members()->attach($member->getKey(), [
            'created_by' => $createdBy?->getKey(),
            'created_at' => now(),
        ]);

        return $this;
    }

    public function removeMember(Member $member): self
    {
        $this->members()->detach($member->getKey());

        return $this;
    }

    public function isAccessibleBy(Member $member): bool
    {
        if (! $member->is_active) {
            return false;
        }

        if (! $this->isPrivate()) {
            return true;
        }

        return $this->hasMember($member);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'type' => 'integer',
            'tenant_id' => 'integer',
        ];
    }
}
