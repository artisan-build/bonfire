<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Enums\BonfireRole;
use ArtisanBuild\Bonfire\Enums\RoomType;
use ArtisanBuild\Bonfire\Events\MessageEdited;
use ArtisanBuild\Bonfire\Events\PollVoted;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\PollVote;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

return new class extends Component
{
    use WithPagination;

    public Room $room;

    public int $perPage = 40;

    public string $search = '';

    public function mount(Room $room): void
    {
        $this->room = $room;
    }

    #[On('bonfire-search')]
    public function setSearch(string $value): void
    {
        $this->search = trim($value);
        $this->resetPage();
        unset($this->messages);
    }

    /**
     * Paginated list of root messages in the room.
     *
     * @return CursorPaginator<int, Message>
     */
    #[Computed]
    public function messages(): CursorPaginator
    {
        $query = Message::query()
            ->with(['member', 'replies', 'attachments', 'linkPreview', 'pollVotes.member'])
            ->where('room_id', $this->room->getKey())
            ->whereNull('parent_id')
            ->where(fn ($q) => $q->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now()));

        if ($this->search !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], $this->search).'%';
            $query->where('body', 'like', $needle);
        }

        return $query->oldest()->cursorPaginate($this->perPage);
    }

    #[On('echo-presence:bonfire.room.{room.id},.message.posted')]
    public function onMessagePosted(): void
    {
        unset($this->messages);
    }

    #[On('echo-presence:bonfire.room.{room.id},.message.deleted')]
    public function onMessageDeleted(): void
    {
        unset($this->messages);
    }

    #[On('bonfire:member-updated')]
    public function onMemberUpdated(): void
    {
        unset($this->messages);
    }

    public function openThread(int $messageId): void
    {
        $this->dispatch('thread-open', messageId: $messageId);
    }

    #[Computed]
    public function currentMember(): ?Member
    {
        return Bonfire::memberFor(auth()->user());
    }

    public function canDelete(Message $message): bool
    {
        $member = $this->currentMember();

        if ($member === null || ! $member->is_active) {
            return false;
        }

        if ($message->member_id === $member->getKey()) {
            return true;
        }

        return $member->hasRoleAtLeast(BonfireRole::Moderator);
    }

    public function deleteMessage(int $messageId): void
    {
        $message = Message::query()->findOrFail($messageId);

        abort_unless($this->canDelete($message), 403);

        $message->delete();

        unset($this->messages);
    }

    public function canPin(Message $message): bool
    {
        $member = $this->currentMember();
        if ($member === null || ! $member->is_active) {
            return false;
        }
        if ($message->trashed()) {
            return false;
        }
        if ($this->room->isPrivate() && ! $this->room->hasMember($member)) {
            return false;
        }

        return true;
    }

    public function togglePin(int $messageId): void
    {
        $message = Message::query()->findOrFail($messageId);
        abort_unless($this->canPin($message), 403);

        if ($message->pinned_at === null) {
            $message->pinned_at = Carbon::now();
            $message->pinned_by_member_id = $this->currentMember()?->id;
        } else {
            $message->pinned_at = null;
            $message->pinned_by_member_id = null;
        }
        $message->save();

        broadcast(new MessageEdited($message))->toOthers();

        unset($this->messages);
        $this->dispatch('bonfire:pins-changed', roomId: $this->room->id);
    }

    public function canEdit(Message $message): bool
    {
        $member = $this->currentMember();

        if ($member === null || ! $member->is_active) {
            return false;
        }

        if ($message->trashed()) {
            return false;
        }

        // Only the author can edit. Admins/mods can still delete, but editing
        // someone else's words would be weird.
        return $message->member_id === $member->getKey();
    }

    public function editMessage(int $messageId, string $body): void
    {
        $message = Message::query()->findOrFail($messageId);

        abort_unless($this->canEdit($message), 403);

        $clean = trim($body);
        if ($clean === '' || $clean === '<p></p>') {
            return; // no-op on empty edit; user can delete instead
        }

        $message->body = $clean;
        $message->save();

        broadcast(new MessageEdited($message))->toOthers();

        unset($this->messages);
    }

    #[On('echo-presence:bonfire.room.{room.id},.message.edited')]
    public function onMessageEdited(): void
    {
        unset($this->messages);
    }

    /**
     * @return Collection<int, Room>
     */
    #[Computed]
    public function forwardableRooms(): Collection
    {
        $member = $this->currentMember();

        if ($member === null) {
            return collect();
        }

        $memberRoomIds = DB::table('bonfire_member_room')
            ->where('member_id', $member->id)
            ->pluck('room_id');

        return Room::query()
            ->where('tenant_id', Bonfire::tenantId())
            ->whereNull('deleted_at')
            ->where('type', '!=', RoomType::Archived->value)
            ->where(function ($q) use ($memberRoomIds): void {
                $q->where('is_private', false)->orWhereIn('id', $memberRoomIds);
            })
            ->where('slug', 'not like', 'dm-%')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'is_private', 'type'])
            ->values();
    }

    public function forwardMessage(int $messageId, int $targetRoomId, string $note = ''): void
    {
        $source = Message::query()->with('member')->findOrFail($messageId);
        $target = Room::query()->findOrFail($targetRoomId);
        $member = $this->currentMember();

        abort_unless($member !== null && $member->is_active, 403);

        if ($target->isPrivate() && ! $target->hasMember($member)) {
            abort(403);
        }

        if ($target->isArchived()) {
            abort(403);
        }

        /** @var Room|null $sourceRoom */
        $sourceRoom = $source->room;

        /** @var Member|null $sourceMember */
        $sourceMember = $source->member;

        // Normalize quoted body: strip tags, decode entities, trim, cap to 300 chars.
        $plainBody = trim(html_entity_decode(strip_tags((string) $source->body), ENT_QUOTES | ENT_HTML5));
        $quotedBody = Str::limit($plainBody, 300);

        $noteClean = Str::limit(trim($note), 2000);
        $author = e($sourceMember->display_name ?? 'Unknown');
        $authorAvatar = e($sourceMember->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($sourceMember->display_name ?? '?'));
        $roomName = e($sourceRoom->name ?? 'unknown');
        $jumpUrl = $sourceRoom ? route('bonfire.room.show', $sourceRoom).'#m-'.$source->id : '#';
        $timestamp = $source->created_at->format('M j, g:i A');

        $html = '';
        if ($noteClean !== '') {
            $html .= '<p>'.e($noteClean).'</p>';
        }
        $html .= '<blockquote data-bonfire-forward="'.$source->id.'" class="bonfire-forward">';
        $html .= '<div class="bonfire-forward-head">';
        $html .= '<img src="'.$authorAvatar.'" alt="" class="bonfire-forward-avatar">';
        $html .= '<span class="bonfire-forward-author">'.$author.'</span>';
        $html .= '<span class="bonfire-forward-meta">in #'.$roomName;
        if ($timestamp !== '') {
            $html .= ' · '.e($timestamp);
        }
        $html .= '</span>';
        $html .= '</div>';
        $html .= '<div class="bonfire-forward-body">'.nl2br(e($quotedBody)).'</div>';
        $html .= '<div class="bonfire-forward-foot"><a href="'.e($jumpUrl).'" data-bonfire-forward-jump>View original →</a></div>';
        $html .= '</blockquote>';

        Bonfire::postAs($member, $target, $html);

        $this->dispatch('bonfire:rooms-changed');
        $this->dispatch('bonfire-forwarded', targetRoomId: $target->id, targetRoomSlug: $target->slug);
    }

    public function togglePollVote(int $messageId, int $optionIndex): void
    {
        $message = Message::query()->findOrFail($messageId);

        if (! $message->isPoll()) {
            return;
        }

        if ((int) $message->room_id !== (int) $this->room->getKey()) {
            abort(403);
        }

        $member = $this->currentMember();
        abort_unless($member !== null && $member->is_active, 403);

        if ($this->room->isPrivate() && ! $this->room->hasMember($member)) {
            abort(403);
        }

        $optionCount = count($message->poll['options'] ?? []);
        if ($optionIndex < 0 || $optionIndex >= $optionCount) {
            return;
        }

        $existing = PollVote::query()
            ->where('message_id', $message->id)
            ->where('member_id', $member->id)
            ->first();

        if ($existing !== null && (int) $existing->option_index === $optionIndex) {
            $existing->delete();
        } else {
            PollVote::query()->updateOrCreate(
                ['message_id' => $message->id, 'member_id' => $member->id],
                ['option_index' => $optionIndex, 'created_at' => now()],
            );
        }

        broadcast(new PollVoted($message->id, (int) $message->room_id))->toOthers();

        unset($this->messages);
    }

    #[On('echo-presence:bonfire.room.{room.id},.poll.voted')]
    public function onPollVoted(): void
    {
        unset($this->messages);
    }
};
