<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Actions;

use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\RoomAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

/**
 * Build the forward blockquote for a source message and post it into a target
 * room as the forwarding member.
 *
 * The quoted body is stripped of markup, decoded, trimmed, and capped at 300
 * characters; an optional note (capped at 2000 characters) is rendered above
 * the quote. The Livewire component keeps all authorization and room guards and
 * receives validated inputs; this action only constructs and posts the HTML.
 */
final class ForwardMessage
{
    public function __invoke(Member $member, Message $source, Room $target, string $note = ''): Message
    {
        /** @var Room|null $sourceRoom */
        $sourceRoom = $source->room;

        if (! $sourceRoom instanceof Room) {
            throw new AuthorizationException('The source message has no accessible room.');
        }

        RoomAccess::authorizeGate($sourceRoom, $member);
        RoomAccess::authorizeGate($target, $member);

        /** @var Member|null $sourceMember */
        $sourceMember = $source->member;

        // Normalize quoted body: strip tags, decode entities, trim, cap to 300 chars.
        $plainBody = trim(html_entity_decode(strip_tags((string) $source->body), ENT_QUOTES | ENT_HTML5));
        $quotedBody = Str::limit($plainBody, 300);

        $noteClean = Str::limit(trim($note), 2000);
        $author = e($sourceMember->display_name ?? 'Unknown');
        $authorAvatar = e($sourceMember->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($sourceMember->display_name ?? '?'));
        $roomName = e($sourceRoom->name);
        $jumpUrl = route('bonfire.room.show', $sourceRoom).'#m-'.$source->id;
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
        $html .= ' · '.e($timestamp);
        $html .= '</span>';
        $html .= '</div>';
        $html .= '<div class="bonfire-forward-body">'.nl2br(e($quotedBody)).'</div>';
        $html .= '<div class="bonfire-forward-foot"><a href="'.e($jumpUrl).'" data-bonfire-forward-jump>View original →</a></div>';
        $html .= '</blockquote>';

        return Bonfire::postAs($member, $target, $html);
    }
}
