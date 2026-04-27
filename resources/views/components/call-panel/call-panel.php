<?php

declare(strict_types=1);

use App\Models\User;
use ArtisanBuild\Bonfire\Events\CallEnded;
use ArtisanBuild\Bonfire\Events\CallInitiated;
use ArtisanBuild\Bonfire\Events\CallSignal;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\CallSession;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use Carbon\CarbonInterface;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    #[Computed]
    public function currentMember(): ?Member
    {
        return Bonfire::memberFor(auth()->user());
    }

    /**
     * Caller initiates a call against another member in a DM room.
     * Returns session id to the client so it can attach SDP/ICE to the correct conversation.
     *
     * @return array{session_id: int, target_user_id: int, target_name: string, target_avatar: ?string}|null
     */
    public function initiateCall(int $roomId, int $calleeMemberId): ?array
    {
        $caller = $this->currentMember();
        if ($caller === null) {
            return null;
        }

        $callee = Member::query()->find($calleeMemberId);
        if ($callee === null || $callee->id === $caller->id) {
            return null;
        }

        $room = Room::query()->find($roomId);
        if ($room === null || ! $room->hasMember($caller) || ! $room->hasMember($callee)) {
            abort(403);
        }

        $targetUserId = $this->resolveUserId($callee);
        if ($targetUserId === null) {
            return null;
        }

        $session = CallSession::query()->create([
            'tenant_id' => Bonfire::tenantId(),
            'room_id' => $room->id,
            'caller_member_id' => $caller->id,
            'callee_member_id' => $callee->id,
            'status' => 'ringing',
        ]);

        broadcast(new CallInitiated(
            $session,
            $caller->display_name,
            $caller->avatar_url,
            $targetUserId,
        ));

        $this->dispatch('bonfire:call-state-changed');

        return [
            'session_id' => $session->id,
            'target_user_id' => $targetUserId,
            'target_name' => $callee->display_name,
            'target_avatar' => $callee->avatar_url,
        ];
    }

    /**
     * Forward SDP or ICE payload to the other peer via broadcast.
     *
     * @param  array<string, mixed>  $payload
     */
    public function relaySignal(int $sessionId, string $kind, array $payload): void
    {
        $caller = $this->currentMember();
        if ($caller === null) {
            return;
        }

        $session = CallSession::query()->find($sessionId);
        if ($session === null) {
            return;
        }

        $peerMemberId = $session->caller_member_id === $caller->id
            ? $session->callee_member_id
            : $session->caller_member_id;

        if (! in_array($caller->id, [$session->caller_member_id, $session->callee_member_id], true)) {
            abort(403);
        }

        $peer = Member::query()->find($peerMemberId);
        $targetUserId = $peer !== null ? $this->resolveUserId($peer) : null;
        if ($targetUserId === null) {
            return;
        }

        if ($kind === 'answer' && $session->status === 'ringing') {
            $session->update(['status' => 'active', 'started_at' => now()]);
        }

        broadcast(new CallSignal($session->id, $targetUserId, $kind, $payload));
    }

    public function endCall(int $sessionId, string $reason = 'ended'): void
    {
        $current = $this->currentMember();
        if ($current === null) {
            return;
        }

        $session = CallSession::query()->find($sessionId);
        if ($session === null) {
            return;
        }

        if (! in_array($current->id, [$session->caller_member_id, $session->callee_member_id], true)) {
            return;
        }

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

        $peerMemberId = $session->caller_member_id === $current->id
            ? $session->callee_member_id
            : $session->caller_member_id;

        $peer = Member::query()->find($peerMemberId);
        $targetUserId = $peer !== null ? $this->resolveUserId($peer) : null;
        if ($targetUserId === null) {
            return;
        }

        broadcast(new CallEnded($session->id, $targetUserId, $reason));

        $this->dispatch('bonfire:call-state-changed');
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
        $body = str_replace('{caller}', $caller->display_name ?? 'Unknown', $body);

        Message::create([
            'tenant_id' => $session->tenant_id,
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

    private function resolveUserId(Member $member): ?int
    {
        if ($member->memberable_type === User::class) {
            return (int) $member->memberable_id;
        }

        return null;
    }
};
