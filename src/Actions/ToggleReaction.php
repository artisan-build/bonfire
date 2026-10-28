<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Events\MessageEdited;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\RoomAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Add or remove a member's reaction with a specific emoji on a message.
 *
 * The unique key is (message_id, member_id, emoji): multiple emoji can coexist
 * for one member, and re-clicking the same emoji removes it. The MessageEdited
 * broadcast notifies other viewers so their reaction summaries refresh.
 *
 * The Livewire component is left to authorize, validate the emoji, and refresh
 * its own computed state.
 */
final class ToggleReaction
{
    public function __invoke(Member $member, Message $message, string $emoji): void
    {
        $room = $message->room;

        if (! $room instanceof Room) {
            throw new AuthorizationException('This message has no accessible room.');
        }

        RoomAccess::authorizeGate($room, $member);

        $existing = DB::table('bonfire_reactions')
            ->where('message_id', $message->getKey())
            ->where('member_id', $member->getKey())
            ->where('emoji', $emoji)
            ->exists();

        if ($existing) {
            DB::table('bonfire_reactions')
                ->where('message_id', $message->getKey())
                ->where('member_id', $member->getKey())
                ->where('emoji', $emoji)
                ->delete();
        } else {
            DB::table('bonfire_reactions')->insert([
                'message_id' => $message->getKey(),
                'member_id' => $member->getKey(),
                'emoji' => $emoji,
                'created_at' => now(),
            ]);
        }

        broadcast(new MessageEdited($message))->toOthers();
    }
}
