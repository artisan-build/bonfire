<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Events\PollVoted;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\PollVote;

/**
 * Cast, switch, or remove a member's poll vote on a message.
 *
 * Re-voting the same option removes the vote; voting a different option updates
 * the existing record. The PollVoted broadcast notifies other viewers so their
 * tallies refresh. The Livewire component keeps the poll/option-range and
 * authorization checks.
 */
final class TogglePollVote
{
    public function __invoke(Member $member, Message $message, int $optionIndex): void
    {
        $existing = PollVote::query()
            ->where('message_id', $message->getKey())
            ->where('member_id', $member->getKey())
            ->first();

        if ($existing !== null && (int) $existing->option_index === $optionIndex) {
            $existing->delete();
        } else {
            PollVote::query()->updateOrCreate(
                ['message_id' => $message->getKey(), 'member_id' => $member->getKey()],
                ['option_index' => $optionIndex, 'created_at' => now()],
            );
        }

        broadcast(new PollVoted($message->getKey(), (int) $message->room_id))->toOthers();
    }
}
