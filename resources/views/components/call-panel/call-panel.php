<?php

declare(strict_types=1);

use App\Models\User;
use ArtisanBuild\Bonfire\Actions\EndCall;
use ArtisanBuild\Bonfire\Actions\InitiateCall;
use ArtisanBuild\Bonfire\Actions\MarkCallActive;
use ArtisanBuild\Bonfire\Events\CallEnded;
use ArtisanBuild\Bonfire\Events\CallInitiated;
use ArtisanBuild\Bonfire\Events\CallSignal;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\CallSession;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
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
        if ($room === null) {
            abort(403);
        }

        $this->authorize('useRealtime', $room);

        // The callee must also be a participant of the room before we ring them.
        if (! $room->hasMember($callee)) {
            abort(403);
        }

        $targetUserId = $this->resolveUserId($callee);
        if ($targetUserId === null) {
            return null;
        }

        $session = app(InitiateCall::class)($room, $caller, $callee);

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

        $room = Room::query()->find($session->room_id);
        if ($room !== null) {
            $this->authorize('useRealtime', $room);
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
            app(MarkCallActive::class)($session);
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

        $room = Room::query()->find($session->room_id);
        if ($room !== null) {
            $this->authorize('useRealtime', $room);
        }

        app(EndCall::class)($session, $current, $reason);

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

    private function resolveUserId(Member $member): ?int
    {
        if ($member->memberable_type === User::class) {
            return (int) $member->memberable_id;
        }

        return null;
    }
};
