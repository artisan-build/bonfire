<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Models;

use ArtisanBuild\Bonfire\Events\MemberMentioned;
use ArtisanBuild\Bonfire\Events\MessageDeleted;
use ArtisanBuild\Bonfire\Events\MessagePosted;
use ArtisanBuild\Bonfire\Jobs\FetchLinkPreview;
use ArtisanBuild\Bonfire\Notifications\MentionedInMessage;
use ArtisanBuild\Bonfire\Support\MarkdownRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Override;

/**
 * Represents a chat message in a Bonfire room.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $room_id
 * @property int $member_id
 * @property int|null $parent_id
 * @property string $body
 * @property array<string, mixed>|null $poll
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $pinned_at
 * @property int|null $pinned_by_member_id
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Message extends Model
{
    use SoftDeletes;

    protected $table = 'bonfire_messages';

    protected $guarded = [];

    #[Override]
    protected static function booted(): void
    {
        static::created(function (self $message): void {
            $message->syncMentions();
            $message->dispatchLinkPreview();

            event(new MessagePosted($message));
        });

        static::deleted(function (self $message): void {
            if ($message->isForceDeleting()) {
                return;
            }

            event(new MessageDeleted($message->id, $message->room_id));
        });
    }

    /**
     * The room this message belongs to.
     *
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    /**
     * The member who authored this message.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * The parent message if this is a reply.
     *
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Reply messages under this parent message.
     *
     * @return HasMany<self, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Emoji reactions on this message.
     *
     * @return HasMany<Reaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class, 'message_id');
    }

    /**
     * File attachments on this message.
     *
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'message_id');
    }

    /**
     * The link preview generated for this message.
     *
     * @return HasOne<LinkPreview, $this>
     */
    public function linkPreview(): HasOne
    {
        return $this->hasOne(LinkPreview::class, 'message_id');
    }

    /**
     * Member mentions parsed from this message.
     *
     * @return HasMany<Mention, $this>
     */
    public function mentions(): HasMany
    {
        return $this->hasMany(Mention::class, 'message_id');
    }

    /**
     * Poll votes cast on this message.
     *
     * @return HasMany<PollVote, $this>
     */
    public function pollVotes(): HasMany
    {
        return $this->hasMany(PollVote::class, 'message_id');
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    public function isPoll(): bool
    {
        return is_array($this->poll)
            && isset($this->poll['question'], $this->poll['options'])
            && is_array($this->poll['options']);
    }

    /**
     * Parse @mentions from body, persist them, and notify mentioned members.
     */
    public function syncMentions(): void
    {
        $renderer = resolve(MarkdownRenderer::class);
        $names = $renderer->extractMentionNames($this->body);

        if ($names === []) {
            return;
        }

        $broadcast = array_values(array_filter(
            $names,
            fn (string $n) => in_array(strtolower($n), ['channel', 'here', 'everyone'], true),
        ));
        $explicit = array_values(array_filter(
            $names,
            fn (string $n) => ! in_array(strtolower($n), ['channel', 'here', 'everyone'], true),
        ));

        $members = collect();

        if ($explicit !== []) {
            $members = $members->merge(
                Member::query()
                    ->whereIn('display_name', $explicit)
                    ->where('tenant_id', $this->tenant_id)
                    ->where('id', '!=', $this->member_id)
                    ->get(),
            );
        }

        // Resolve broadcast tokens into member sets.
        foreach ($broadcast as $token) {
            $t = strtolower($token);
            $query = Member::query()
                ->where('tenant_id', $this->tenant_id)
                ->where('is_active', true)
                ->where('id', '!=', $this->member_id);

            if ($t === 'channel' || $t === 'here') {
                /** @var Room|null $mentionRoom */
                $mentionRoom = $this->room;
                $roomMemberIds = $mentionRoom?->members()->pluck('bonfire_members.id') ?? collect();
                $query->whereIn('id', $roomMemberIds);
                if ($t === 'here') {
                    $query->where('is_away', false);
                }
            }
            // @everyone: no further filter — all active workspace members.

            $members = $members->merge($query->get());
        }

        $members = $members->unique('id')->values();

        if ($members->isEmpty()) {
            return;
        }

        foreach ($members as $member) {
            Mention::query()->insert([
                'message_id' => $this->id,
                'member_id' => $member->id,
                'created_at' => now(),
            ]);
        }

        Notification::send($members, new MentionedInMessage($this));

        /** @var Member|null $author */
        $author = $this->member;
        /** @var Room|null $room */
        $room = $this->room;

        if ($author !== null && $room !== null) {
            foreach ($members as $member) {
                event(new MemberMentioned($member, $author, $this, $room));
            }
        }
    }

    public function dispatchLinkPreview(): void
    {
        if (! (bool) config('bonfire.link_preview_enabled', true)) {
            return;
        }

        // Forwarded messages embed a synthetic "Jump to original" link — never unfurl.
        if (str_contains((string) $this->body, 'data-bonfire-forward')) {
            return;
        }

        $url = FetchLinkPreview::extractFirstUrl($this->body);

        if ($url === null) {
            return;
        }

        dispatch(new FetchLinkPreview($this->id, $url));
    }

    /**
     * Scope query to root messages only (no replies).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'scheduled_for' => 'datetime',
            'pinned_at' => 'datetime',
            'poll' => 'array',
        ];
    }
}
