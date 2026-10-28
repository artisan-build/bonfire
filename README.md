# Bonfire

A **headless** real-time chat engine for Laravel. Bonfire provides the domain
layer (models, actions, policies), an opt-in HTTP/JSON API, and a broadcast
event contract for Slack-style messaging — channels, threads, direct messages,
polls, reactions, file attachments, and more. **Bonfire does not ship a UI.**
The presentation layer is the host application's concern.

> [!WARNING]
> Bonfire is under active development and has not reached a stable release.
> The `0.1.0` release marks the headless boundary: as of this version the
> package ships no views, Livewire, or Flux. Breaking changes may occur in any
> release until 1.0. Pin to a specific version if you use this in production.

## The package vs. the reference app

It's worth being explicit about what this repository contains:

- **Bonfire (the package, `packages/bonfire/`) is the product.** It is a
  headless chat engine: domain models and actions, opt-in HTTP/JSON API, and a
  broadcast contract. It ships **no UI** and **does not require Flux Pro or
  Livewire**.
- **artisan-community (the host repo) is the reference app.** It demonstrates
  Bonfire with a concrete Flux/Livewire UI. The reference app **does** require a
  commercial [Flux UI Pro](https://fluxui.dev) license — that is a property of
  the demo, not of the package.

If you adopt Bonfire, you bring your own UI (Livewire, Inertia, a native client,
anything) and talk to the engine through the PHP API or the HTTP/JSON API.

## Requirements

- PHP 8.3+
- Laravel 11.36+, 12.x, or 13.x
- `league/commonmark` (Markdown rendering)

The package has **no UI dependencies** — it does not require Livewire or Flux.
`laravel/sanctum` is only needed by the host if it enables the HTTP/JSON API.

## Installation

```bash
composer require artisan-build/bonfire
php artisan bonfire:install
```

The install command publishes the config file, migrations, and runs `migrate`.
Run `php artisan migrate` after upgrading an existing installation so rooms gain
the nullable `access_gate` column.

### Prepare your User model

Add the `HasBonfireProfile` trait to your `User` model:

```php
use ArtisanBuild\Bonfire\Traits\HasBonfireProfile;

class User extends Authenticatable
{
    use HasBonfireProfile;
}
```

Override `bonfireDisplayName()` and `bonfireAvatarUrl()` on your User model to customize how members appear in chat.

### Create your first room

```bash
php artisan bonfire:create-room General --description="General discussion"
```

## Features

All features below are **domain/API capabilities** — engine operations exposed
through the PHP and HTTP APIs and the broadcast contract. They are not shipped
screens; the host renders them.

- **Channels** - Public, private, and announcement-only rooms
- **Threads** - Reply to any message in a threaded conversation
- **Direct messages** - Private conversations between members
- **Rich text** - Markdown bodies (bold, italic, strikethrough, lists, links, code blocks) rendered server-side
- **Polls** - Create polls with 2-10 options, attached to messages
- **Reactions** - Emoji reactions on any message
- **File attachments** - Image and document uploads with a secured download route
- **Link previews** - Automatic URL unfurling with metadata
- **@mentions** - Mention members with configurable notifications
- **Message pinning** - Pin important messages to a channel
- **Starred channels** - Star channels for quick access
- **Channel sections** - Organize channels into custom groups
- **Search** - Search messages within a channel
- **Admin operations** - Domain operations and policies for managing rooms, members, and roles (Member/Moderator/Admin) — the engine provides the authorization and actions, not an admin UI
- **Roles** - Member, Moderator, and Admin with hierarchical permissions
- **Single-tenant** - One community per deployment (built-for-cloud); no per-row tenant scoping

## Consuming Bonfire

There are two ways to drive the engine.

### 1. PHP API (in-process)

Resolve the `Bonfire` facade / `BonfireManager`, the action classes in
`ArtisanBuild\Bonfire\Actions`, or the Eloquent models directly. This is the
path the reference app's Livewire components use:

```php
use ArtisanBuild\Bonfire\Facades\Bonfire;

$member  = Bonfire::memberFor($user);
$message = Bonfire::postAs($member, $room, 'Hello, world');
```

### 2. HTTP/JSON API v1 (opt-in)

Set `BONFIRE_API_ENABLED=true` to register the JSON API. Authentication is via
Laravel Sanctum — cookie-based for first-party SPAs, or personal access tokens
for native/third-party clients. The host installs Sanctum and **owns the auth
guard** via `config('bonfire.api.middleware')` (default `['auth:sanctum']`).

Routes are registered under the `bonfire.api.prefix` (default `bonfire/api`):

| Method | URI | Name |
|---|---|---|
| GET | `rooms` | `bonfire.api.rooms.index` |
| GET | `rooms/{room}` | `bonfire.api.rooms.show` |
| GET | `rooms/{room}/messages` | `bonfire.api.rooms.messages.index` |
| POST | `rooms/{room}/messages` | `bonfire.api.rooms.messages.store` |
| PATCH | `messages/{message}` | `bonfire.api.messages.update` |
| DELETE | `messages/{message}` | `bonfire.api.messages.destroy` |
| POST | `messages/{message}/reactions` | `bonfire.api.messages.reactions.store` |
| POST | `messages/{message}/poll/vote` | `bonfire.api.messages.poll.vote` |
| POST | `rooms/{room}/read` | `bonfire.api.rooms.read` |
| POST | `rooms/{room}/members` | `bonfire.api.rooms.members.store` |
| DELETE | `rooms/{room}/members/{member}` | `bonfire.api.rooms.members.destroy` |

## Configuration

Publish the config file if you haven't already:

```bash
php artisan vendor:publish --tag=bonfire-config
```

Key options in `config/bonfire.php`:

| Option | Default | Description |
|---|---|---|
| `route_prefix` | `'bonfire'` | URL prefix for the package's web routes |
| `route_middleware` | `['web', 'auth']` | Middleware applied to the package's web routes |
| `disk` | `'public'` | Storage disk for file attachments |
| `max_attachment_size_kb` | `10240` | Maximum upload size in KB |
| `allowed_attachment_types` | `['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf']` | Accepted MIME types (SVG excluded — XSS vector) |
| `api.enabled` | `env('BONFIRE_API_ENABLED', false)` | Opt in to the HTTP/JSON API v1 |
| `api.prefix` | `'bonfire/api'` | URL prefix for the JSON API |
| `api.middleware` | `['auth:sanctum']` | Auth guard for the JSON API (host-owned) |
| `notification_channels` | `['database']` | Laravel notification channels for mentions |
| `link_preview_enabled` | `true` | Fetch link previews for posted URLs |
| `resolve_member` | Uses `Bonfire::memberFor()` | Callable returning the current `Member` |
| `user_channel` | `fn ($id) => 'App.Models.User.'.$id` | Formats the broadcast channel name for a host user identifier; override to namespace per tenant |

## Routes

The package registers the following **web** route under the configured
`route_prefix` (default: `/bonfire`):

| Method | URI | Name | Description |
|---|---|---|---|
| GET | `/bonfire/attachments/{attachment}` | `bonfire.attachments.show` | Download an attachment |

Page rendering (channel index, room views, admin) lives in the host
application, not the package. When `BONFIRE_API_ENABLED=true`, the opt-in
HTTP/JSON API routes above are also registered (see
[`routes/api.php`](routes/api.php)).

## Room types

Room types use a bitmask system via `RoomType`:

| Value | Type | Description |
|---|---|---|
| `0` | Public | Visible to all members (default) |
| `1` | Private | Invite-only, hidden from non-members |
| `2` | Archived | Read-only, hidden from sidebar |
| `4` | Announcements | Only moderators and admins can post |

Flags can be combined: a private announcements room would have type `5` (1 + 4).

### Named room access gates

A host can put a live access rule in front of a room without creating or removing
room memberships. Register the rule from a service provider:

```php
use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;

Bonfire::gate('paid', fn (Member $member): bool => $member->memberable->hasActiveSubscription());
```

Set the registered name when creating or updating a room:

```php
use ArtisanBuild\Bonfire\Models\Room;

$room = Room::query()->create([
    'name' => 'Subscribers',
    'slug' => 'subscribers',
    'type' => 0,
    'access_gate' => 'paid',
    'created_by' => $admin->id,
]);
```

The room's usual active-member and public-or-membership rules still apply. The
named resolver must also return `true`. An unknown gate or a resolver that throws
denies access. Results are cached once per gate and member during a request, then
evaluated again on the next request. Gate evaluation never changes room
memberships.

An ungated public room keeps its existing behavior, including anonymous attachment
downloads. A gated public room requires a resolved member. Hosts should use
`$room->isAccessibleBy($member)` for page routes, searches, sidebars, and broadcast
channel authorization so those surfaces make the same decision as Bonfire's API.

## Roles

Members have one of three roles, defined in `BonfireRole`:

| Role | Permissions |
|---|---|
| **Member** | Send messages, react, upload files, create threads |
| **Moderator** | All member permissions + pin messages, post in announcement rooms |
| **Admin** | All moderator permissions + manage rooms, members, and roles |

## Broadcasting

Bonfire **emits broadcast events** — this is the real-time contract. The host
application wires the broadcaster (Reverb, Pusher, etc.), Laravel Echo on the
client, and the channel authorization. Once wired, the engine drives:

- Live message delivery
- Typing indicators
- Reaction updates
- Poll vote updates
- Call signaling

Channel naming is configurable via the `user_channel` config closure so the
host's existing channel authorization keeps working unchanged. For hosts that
cannot run a WebSocket server, a **polling fallback** is available through the
HTTP/JSON API.

See [`docs/realtime.md`](docs/realtime.md) for the full event contract (all
broadcast events, channel names, and payloads), the channel authorization your
host app must wire, and the HTTP forward-poll fallback.

## License

MIT
