<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Models\CallSession;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\RoomAccess;
use ArtisanBuild\Bonfire\Support\SafeMessageHtml;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Close a call session, recording the appropriate terminal status and posting a
 * call-summary message into the room. A session that is already closed
 * (ended/declined/missed) is left untouched and posts no second summary.
 *
 * The Livewire component keeps the participant guard, authorization, host-user
 * id resolution, and the CallEnded broadcast; this action only performs the
 * package-side status transition and summary message.
 */
final class EndCall
{
    public function __invoke(CallSession $session, Member $current, string $reason): CallSession
    {
        $room = $session->room;

        if (! $room instanceof Room) {
            throw new AuthorizationException('This call has no accessible room.');
        }

        RoomAccess::authorizeGate($room, $current);

        $alreadyClosed = in_array($session->status, ['ended', 'declined', 'missed'], true);

        if (! $alreadyClosed) {
            // Decide the recorded status based on who ended and when.
            $status = match (true) {
                $reason === 'declined' => 'declined',
                $reason === 'missed' => 'missed',
                // Caller cancelled while still ringing → callee sees it as missed.
                $reason === 'canceled' && $session->status === 'ringing' && $session->caller_member_id === $current->id => 'missed',
                default => 'ended',
            };

            $session->update([
                'status' => $status,
                'ended_at' => now(),
            ]);

            $this->postCallSummary($session->fresh());
        }

        return $session;
    }

    private function postCallSummary(CallSession $session): void
    {
        [$icon, $body] = match ($session->status) {
            'missed' => ['📞', '**Missed call** from {caller}'],
            'declined' => ['📵', 'Call declined'],
            'ended' => ['📞', 'Call ended'.($session->started_at
                ? ' · '.$this->formatDuration($session->started_at, $session->ended_at)
                : ''),
            ],
            default => [null, null],
        };

        if ($body === null) {
            return;
        }

        $caller = Member::query()->find($session->caller_member_id);
        $body = str_replace('{caller}', SafeMessageHtml::escape($caller->display_name ?? 'Unknown'), $body);

        Message::create([
            'room_id' => $session->room_id,
            'member_id' => $session->caller_member_id,
            'body' => $icon.' '.$body,
        ]);
    }

    private function formatDuration(CarbonInterface $start, ?CarbonInterface $end): string
    {
        $endTs = ($end ?? now())->getTimestamp();
        $seconds = max(0, $endTs - $start->getTimestamp());
        $m = intdiv($seconds, 60);
        $s = $seconds % 60;

        return sprintf('%d:%02d', $m, $s);
    }
}
