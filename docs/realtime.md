# Real-time contract

Bonfire is **headless** about transport. The package **emits Laravel broadcast
events** with stable channel names, `broadcastAs` aliases, and payloads — that is
the contract. It does **not** ship a broadcaster, channel authorization, or a
client. The **host application wires**:

- a broadcaster (Reverb, Pusher, Ably, …) in `config/broadcasting.php`,
- the channel authorization callbacks in `routes/channels.php`,
- the client subscription (Laravel Echo, or anything that speaks the chosen driver).

| Responsibility | Owner |
|---|---|
| Channel **names** + `broadcastAs` aliases + **payloads** | **Package** (this contract) |
| Broadcaster config, `routes/channels.php` auth callbacks, client wiring | **Host** |

The package never assumes a specific broadcaster is installed; if the host
configures none, events simply are not delivered and clients fall back to
[polling](#polling-fallback).

## Events

All events live in `packages/bonfire/src/Events/`. Channel names below are the
literal strings the package passes to `PresenceChannel` / `PrivateChannel`.

### Live server broadcasts

These events are dispatched by the package and delivered over the host's
broadcaster. They are the stable v1 contract.

| Event | `broadcastAs` | Channel type | Channel name | Payload fields |
|---|---|---|---|---|
| `MessagePosted` | `message.posted` | Presence | `bonfire.room.{room_id}` | `id`, `room_id`, `parent_id`, `member_id` |
| `MessageEdited` | `message.edited` | Presence | `bonfire.room.{room_id}` | `id`, `room_id`, `parent_id`, `member_id` |
| `MessageDeleted` | `message.deleted` | Presence | `bonfire.room.{room_id}` | `id`, `room_id` |
| `PollVoted` | `poll.voted` | Presence | `bonfire.room.{room_id}` | `message_id` |
| `MemberMentioned` | `member.mentioned` | Private | user channel — see [User channel naming](#user-channel-naming) | `message_id`, `room_id`, `room_slug`, `room_name`, `author_name`, `author_avatar`, `preview` |

Payloads are intentionally thin — an id plus enough context to invalidate or
re-fetch. A client receiving `message.posted` typically re-fetches the message
(or the room tail) over the HTTP API; the event is a signal, not the full record.

- **`MessageEdited`** also covers **reactions and pins** — `ToggleReaction`,
  `TogglePin`, and `EditMessage` all broadcast `message.edited`. On receipt,
  re-fetch the message to refresh its body, reaction summary, and pin state.
- **`MemberMentioned`** broadcasts on the **mentioned user's private channel**.
  `broadcastOn()` returns `[]` (no broadcast) when the mentioned member has a
  `null` `memberable_type` or `memberable_id` — i.e. members not backed by a
  host user receive no mention push.

### Typing indicator — client whisper (not a server broadcast)

Typing is **not** dispatched by the server. The reference client sends it
peer-to-peer as an Echo **whisper** on the room presence channel:

| Event name | Transport | Channel | Payload (client-defined) |
|---|---|---|---|
| `user.typing` | Echo whisper | `bonfire.room.{room_id}` (presence) | `member_id`, `display_name` |

The composer calls `.whisper('user.typing', {...})` and the message list
`.listenForWhisper('user.typing', ...)`. The payload is set **client-side**, so
it is whatever the sender chooses (the reference client sends `member_id` +
`display_name`) — it is not produced by any PHP event's `broadcastWith()`.

> ⚠️ A `UserTyping` `ShouldBroadcast` class exists in `src/Events/`, but it is
> **not server-dispatched** in v1. The contract for typing is the client whisper
> above, not that class.

### Defined but not server-emitted in v1

These event classes exist in `src/Events/` but are **not dispatched** by any
package code path. Do not subscribe to them as live events — they are
forward-looking or superseded.

| Event | `broadcastAs` | Channel type | Channel name | Payload fields | Status |
|---|---|---|---|---|---|
| `ReactionToggled` ⚠️ | `reaction.toggled` | Presence | `bonfire.room.{room_id}` | `message_id`, `member_id`, `count`, `active` | superseded by `message.edited` |
| `CallInitiated` ⚠️ | `call.initiated` | Private | user channel | `sessionId`, `roomId`, `callerMemberId`, `callerName`, `callerAvatar`, `targetUserId` | deferred |
| `CallEnded` ⚠️ | `call.ended` | Private | user channel | `sessionId`, `targetUserId`, `reason` | deferred |
| `CallSignal` ⚠️ | `call.signal` | Private | user channel | `sessionId`, `targetUserId`, `kind`, `payload` | deferred |

- **`ReactionToggled`** is **not wired in v1.** Reaction add/remove currently
  broadcasts **`message.edited`** (`MessageEdited`) via `ToggleReaction`;
  subscribe to `.message.edited` and re-fetch the message to refresh reaction
  state. The `ReactionToggled` class exists but is not dispatched.
- **Call events** (`CallInitiated`, `CallEnded`, `CallSignal`) define **no
  `broadcastWith()`** method, so if they were ever dispatched Laravel would
  serialize all client-visible public properties (the internal socket id is
  omitted) — those are the payload fields above (note the `camelCase` keys,
  unlike the snake_case room-event payloads). For `CallSignal`, `payload` is an
  `array<string, mixed>` carrying the SDP offer/answer or ICE candidate.

> ⚠️ **Calls/meetings are deferred from v1.** The three `Call*` events are
> documented here as a forward-looking contract but are **experimental** — no
> real Reverb/Echo signaling path is wired in v1. Treat them as unstable.

### User channel naming

Private events target a **host user channel**, not a Bonfire member channel. The
name is config-driven so hosts can namespace per tenant:

```php
// packages/bonfire/config/bonfire.php
'user_channel' => fn ($id) => 'App.Models.User.'.$id,
```

Events resolve it through the facade:

```php
ArtisanBuild\Bonfire\Facades\Bonfire::userChannelName($id); // default: "App.Models.User.{id}"
```

The default matches Laravel's `App.Models.User.{id}` convention, so a host's
existing user-channel authorization keeps working unchanged. Override the closure
(e.g. `fn ($id) => 'tenant.'.$tenantId.'.user.'.$id`) to namespace channels —
the authorization callback in `routes/channels.php` must match whatever the
closure produces. The config-driven naming and `userChannelName()` resolver were
introduced in PR2.

## Channel authorization (host wires this)

The package emits on channel **names**; the host must authorize those channels in
`routes/channels.php`. Two channels need callbacks:

```php
use App\Models\User;
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Support\Facades\Broadcast;

// Private user channel — must match config('bonfire.user_channel').
// Default produces "App.Models.User.{id}", so the wildcard below lines up.
Broadcast::channel('App.Models.User.{userId}', function (User $user, int $userId) {
    return (int) $user->id === $userId;
});

// Room presence channel — return the member's identity array to populate the
// presence roster. Returning a falsy value denies the subscription.
Broadcast::channel('bonfire.room.{roomId}', function (User $user, int $roomId) {
    $member = Bonfire::memberFor($user);
    $room = Room::query()->find($roomId);

    if ($member === null || $room === null || ! $room->isAccessibleBy($member)) {
        return false;
    }

    return [
        'id' => $member->id,
        'display_name' => $member->display_name,
        'avatar_url' => $member->avatar_url,
    ];
});
```

The exact identity lookup is the host's call. Keep `Room::isAccessibleBy()` in the
presence callback: it applies active-member status, public or private membership,
and any named room access gate. Replacing it with a pivot-only check can admit a
member whose named gate now denies access. The contract requires that:

- the **private** callback authorizes the channel name produced by
  `config('bonfire.user_channel')`, and
- the **presence** callback authorizes `bonfire.room.{id}` and returns an
  identity array for the presence roster.

## Client subscription (non-Livewire)

Any client that speaks the host's broadcaster works. With
[laravel-echo](https://github.com/laravel/echo) + Reverb/Pusher:

```js
import Echo from 'laravel-echo'

// Room presence channel — note the LEADING DOT: broadcastAs sets a custom
// event name, and Echo requires the dot prefix to match it verbatim.
Echo.join(`bonfire.room.${roomId}`)
  .here((members) => { /* presence roster */ })
  .listen('.message.posted', (e) => { /* e = payload, e.g. { id, room_id, ... } */ })
  // Reactions (and pins) arrive as message.edited — re-fetch to refresh state.
  .listen('.message.edited', (e) => { /* re-fetch message e.id */ })
  // Typing is a peer-to-peer whisper, not a server broadcast (no leading dot).
  .listenForWhisper('user.typing', (e) => { /* e.member_id, e.display_name */ })

// Private user channel — mentions (and, later, call signaling).
Echo.private(`App.Models.User.${userId}`)
  .listen('.member.mentioned', (e) => { /* e.room_slug, e.preview, ... */ })
```

Echo configuration (broadcaster key, and the auth endpoint — the host's
`/broadcasting/auth`) is host responsibility. Use the same channel name your
`user_channel` closure produces for `Echo.private(...)`.

## Polling fallback

When no broadcaster is configured (or a client cannot hold a socket), poll the
HTTP API for new messages. `GET bonfire/api/rooms/{room}/messages` accepts an
optional `after` query parameter:

- **`after` absent** — newest-first, cursor-paginated (back-scroll / initial load).
- **`after={id}`** — only messages with `id > after`, **ascending** (chronological
  new arrivals). `after` is validated as `sometimes|integer|min:1`.

A minimal forward-poll loop tracks the highest message id it has seen and asks
for anything newer:

```js
let lastSeenId = /* max id from the initial load */

async function poll() {
  const res = await fetch(`/bonfire/api/rooms/${roomId}/messages?after=${lastSeenId}`, {
    headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
  })
  const { data } = await res.json() // ascending by id

  for (const message of data) {
    appendMessage(message)
    lastSeenId = Math.max(lastSeenId, message.id)
  }
}

setInterval(poll, 5000)
```

The endpoint enforces the same authorization as the rest of the message API
(`RoomPolicy@view`), so forward-polling a private room you are not a member of
returns `403`.
