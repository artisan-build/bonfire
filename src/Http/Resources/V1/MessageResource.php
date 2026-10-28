<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Http\Resources\V1;

use ArtisanBuild\Bonfire\Http\Resources\V1\Concerns\ResolvesCurrentMember;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\RoomAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * The stable v1 contract for a chat message.
 *
 * `body` is the RAW markdown source straight from the column — clients render
 * AND sanitize it. The package never converts it to HTML for this API. Reaction
 * and poll state are embedded so clients need no follow-up requests.
 *
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    use ResolvesCurrentMember;

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $member = $this->currentMember($request);
        $room = $this->room;

        if (! $room instanceof Room) {
            throw new AuthorizationException('This message has no accessible room.');
        }

        $roomKey = 'bonfire_authorized_room_'.$room->getKey();

        if ($request->attributes->get($roomKey) !== true) {
            RoomAccess::authorizeGate($room, $member);
            $request->attributes->set($roomKey, true);
        }

        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'parent_id' => $this->parent_id,
            'body' => $this->body,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'pinned_at' => $this->pinned_at?->toISOString(),
            'member' => new MemberResource($this->whenLoaded('member')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'reactions' => $this->reactionState($member),
            'poll' => $this->pollState($member),
        ];
    }

    /**
     * Aggregate reactions into emoji + count + whether the current member reacted.
     *
     * @return list<array{emoji: string, count: int, reacted: bool}>
     */
    protected function reactionState(?Member $member): array
    {
        if (! $this->relationLoaded('reactions')) {
            return [];
        }

        $grouped = [];

        foreach ($this->reactions as $reaction) {
            $emoji = (string) $reaction->emoji;
            $grouped[$emoji] ??= ['emoji' => $emoji, 'count' => 0, 'reacted' => false];
            $grouped[$emoji]['count']++;

            if ($member !== null && (int) $reaction->member_id === $member->getKey()) {
                $grouped[$emoji]['reacted'] = true;
            }
        }

        return array_values($grouped);
    }

    /**
     * The poll definition with per-option vote counts and the current member's
     * vote. Returns null when the message is not a poll.
     *
     * @return array{question: string, options: list<array{index: int, text: string, votes: int, voted: bool}>, my_vote: int|null}|null
     */
    protected function pollState(?Member $member): ?array
    {
        if (! $this->isPoll()) {
            return null;
        }

        /** @var array<string, mixed> $poll */
        $poll = $this->poll;
        /** @var list<string> $options */
        $options = array_values($poll['options']);

        $counts = [];
        $myVote = null;

        if ($this->relationLoaded('pollVotes')) {
            foreach ($this->pollVotes as $vote) {
                $index = (int) $vote->option_index;
                $counts[$index] = ($counts[$index] ?? 0) + 1;

                if ($member !== null && (int) $vote->member_id === $member->getKey()) {
                    $myVote = $index;
                }
            }
        }

        $optionState = [];

        foreach ($options as $index => $text) {
            $optionState[] = [
                'index' => $index,
                'text' => (string) $text,
                'votes' => $counts[$index] ?? 0,
                'voted' => $myVote === $index,
            ];
        }

        return [
            'question' => (string) ($poll['question'] ?? ''),
            'options' => $optionState,
            'my_vote' => $myVote,
        ];
    }
}
