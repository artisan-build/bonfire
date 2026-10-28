<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Http\Controllers\Api\V1;

use ArtisanBuild\Bonfire\Actions\DeleteMessage;
use ArtisanBuild\Bonfire\Actions\EditMessage;
use ArtisanBuild\Bonfire\Actions\SendMessage;
use ArtisanBuild\Bonfire\Actions\TogglePollVote;
use ArtisanBuild\Bonfire\Actions\ToggleReaction;
use ArtisanBuild\Bonfire\Http\Requests\Api\V1\EditMessageRequest;
use ArtisanBuild\Bonfire\Http\Requests\Api\V1\ListMessagesRequest;
use ArtisanBuild\Bonfire\Http\Requests\Api\V1\ReactRequest;
use ArtisanBuild\Bonfire\Http\Requests\Api\V1\SendMessageRequest;
use ArtisanBuild\Bonfire\Http\Requests\Api\V1\VotePollRequest;
use ArtisanBuild\Bonfire\Http\Resources\V1\MessageResource;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    /**
     * Relations every single-message response eager-loads for the resource.
     *
     * @var list<string>
     */
    private const MESSAGE_RELATIONS = ['member', 'room', 'reactions', 'attachments', 'pollVotes'];

    /**
     * All messages in a room (roots and replies), newest-first, cursor-paginated.
     * Clients thread using each resource's parent_id.
     *
     * Pass an `after` message id to forward-poll instead: only messages newer
     * than that id are returned, ascending (chronological new arrivals).
     */
    public function index(ListMessagesRequest $request, Room $room): AnonymousResourceCollection
    {
        $this->authorize('view', $room);
        $request->attributes->set('bonfire_authorized_room_'.$room->getKey(), true);

        $query = $room->messages()->with(self::MESSAGE_RELATIONS);

        $after = $request->integer('after');

        if ($after > 0) {
            $query->where('id', '>', $after)->orderBy('id');
        } else {
            $query->orderByDesc('id');
        }

        return MessageResource::collection($query->cursorPaginate());
    }

    public function store(SendMessageRequest $request, Room $room, SendMessage $sendMessage): MessageResource
    {
        $this->authorize('post', $room);

        $member = $this->actingMember();
        abort_if($member === null, 403);

        $parent = $this->resolveParent($request->input('parent_id'), $room);
        $poll = $this->normalizePoll($request->input('poll'));

        $body = trim((string) $request->input('body', ''));

        if ($body === '' && $poll !== null) {
            $body = $poll['question'];
        }

        $message = $sendMessage($member, $room, $body, $parent, null, $poll, [], [], false);

        return new MessageResource($message->load(self::MESSAGE_RELATIONS));
    }

    public function update(EditMessageRequest $request, Message $message, EditMessage $editMessage): MessageResource
    {
        $this->authorize('update', $message);

        $member = $this->actingMember();
        abort_if($member === null, 403);

        $editMessage($message, trim((string) $request->input('body')), $member);

        return new MessageResource($message->fresh(self::MESSAGE_RELATIONS));
    }

    public function destroy(Message $message, DeleteMessage $deleteMessage): Response
    {
        $this->authorize('delete', $message);

        $member = $this->actingMember();
        abort_if($member === null, 403);

        $deleteMessage($message, $member);

        return response()->noContent();
    }

    public function react(ReactRequest $request, Message $message, ToggleReaction $toggleReaction): MessageResource
    {
        $this->authorize('react', $message);

        $member = $this->actingMember();
        abort_if($member === null, 403);

        $toggleReaction($member, $message, trim((string) $request->input('emoji')));

        return new MessageResource($message->fresh(self::MESSAGE_RELATIONS));
    }

    public function vote(VotePollRequest $request, Message $message, TogglePollVote $togglePollVote): MessageResource
    {
        $this->authorize('vote', $message);

        $member = $this->actingMember();
        abort_if($member === null, 403);

        if (! $message->isPoll()) {
            throw ValidationException::withMessages(['option_index' => 'This message is not a poll.']);
        }

        $optionIndex = (int) $request->input('option_index');
        $optionCount = count($message->poll['options'] ?? []);

        if ($optionIndex >= $optionCount) {
            throw ValidationException::withMessages(['option_index' => 'The selected option does not exist.']);
        }

        $togglePollVote($member, $message, $optionIndex);

        return new MessageResource($message->fresh(self::MESSAGE_RELATIONS));
    }

    /**
     * Resolve and validate an optional parent message: it must exist, belong to
     * the same room, and be a root (replies cannot be nested).
     */
    private function resolveParent(mixed $parentId, Room $room): ?Message
    {
        if ($parentId === null) {
            return null;
        }

        $parent = Message::query()->find($parentId);

        if ($parent === null || (int) $parent->room_id !== (int) $room->getKey() || $parent->isReply()) {
            throw ValidationException::withMessages(['parent_id' => 'The parent message is invalid for this room.']);
        }

        return $parent;
    }

    /**
     * Normalize the optional poll payload into the persisted shape, dropping
     * blank options and requiring at least two.
     *
     * @return array{question: string, options: list<string>}|null
     */
    private function normalizePoll(mixed $poll): ?array
    {
        if (! is_array($poll)) {
            return null;
        }

        $options = array_values(array_filter(
            array_map(fn ($option): string => trim((string) $option), $poll['options'] ?? []),
            fn (string $option): bool => $option !== '',
        ));

        if (count($options) < 2) {
            throw ValidationException::withMessages(['poll.options' => 'A poll needs at least 2 non-empty options.']);
        }

        return [
            'question' => trim((string) ($poll['question'] ?? '')),
            'options' => array_slice($options, 0, 10),
        ];
    }
}
