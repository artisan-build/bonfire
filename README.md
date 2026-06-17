# Bonfire

A real-time chat package for Laravel, built with Livewire. Bonfire gives your application Slack-style messaging with channels, threads, direct messages, polls, reactions, file attachments, and more.

> [!WARNING]
> Bonfire is under active development and has not reached a stable release. Breaking changes may occur in any release until 1.0 is tagged. Pin to a specific version if you use this in production.

## Requirements

- PHP 8.2+
- Laravel 11.36+, 12.x, or 13.x
- Livewire 4.0+

## Installation

```bash
composer require artisan-build/bonfire
php artisan bonfire:install
```

The install command publishes the config file, migrations, and runs `migrate`.

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

- **Channels** - Public, private, and announcement-only rooms
- **Threads** - Reply to any message in a threaded side panel
- **Direct messages** - Private conversations between members
- **Rich text composer** - Bold, italic, strikethrough, lists, links, code blocks
- **Polls** - Create polls with 2-10 options, attached to messages
- **Reactions** - Emoji reactions on any message
- **File attachments** - Upload images and documents
- **Link previews** - Automatic URL unfurling with metadata
- **@mentions** - Mention members with desktop and in-app notifications
- **Message pinning** - Pin important messages to a channel
- **Starred channels** - Star channels for quick sidebar access
- **Channel sections** - Organize channels into custom sidebar groups
- **Search** - Search messages within a channel
- **Admin panel** - Manage rooms, members, and roles
- **Roles** - Member, Moderator, and Admin with hierarchical permissions
- **Single-tenant** - One community per deployment (built-for-cloud); no per-row tenant scoping

## Configuration

Publish the config file if you haven't already:

```bash
php artisan vendor:publish --tag=bonfire-config
```

Key options in `config/bonfire.php`:

| Option | Default | Description |
|---|---|---|
| `route_prefix` | `'bonfire'` | URL prefix for all Bonfire routes |
| `route_middleware` | `['web', 'auth']` | Middleware applied to Bonfire routes |
| `disk` | `'public'` | Storage disk for file attachments |
| `max_attachment_size_kb` | `10240` | Maximum upload size in KB |
| `allowed_attachment_types` | `['image/*', 'application/pdf']` | Accepted MIME types |
| `notification_channels` | `['database']` | Laravel notification channels for mentions |
| `link_preview_enabled` | `true` | Fetch link previews for posted URLs |
| `resolve_member` | Uses `Bonfire::memberFor()` | Callable returning the current `Member` |

## Routes

All routes are registered under the configured `route_prefix` (default: `/bonfire`):

| Method | URI | Name | Description |
|---|---|---|---|
| GET | `/bonfire` | `bonfire.index` | Channel index |
| GET | `/bonfire/admin` | `bonfire.admin.index` | Admin panel |
| GET | `/bonfire/{room:slug}` | `bonfire.room.show` | View a channel |
| GET | `/bonfire/attachments/{attachment}` | `bonfire.attachments.show` | Download an attachment |

## Room types

Room types use a bitmask system via `RoomType`:

| Value | Type | Description |
|---|---|---|
| `0` | Public | Visible to all members (default) |
| `1` | Private | Invite-only, hidden from non-members |
| `2` | Archived | Read-only, hidden from sidebar |
| `4` | Announcements | Only moderators and admins can post |

Flags can be combined: a private announcements room would have type `5` (1 + 4).

## Roles

Members have one of three roles, defined in `BonfireRole`:

| Role | Permissions |
|---|---|
| **Member** | Send messages, react, upload files, create threads |
| **Moderator** | All member permissions + pin messages, post in announcement rooms |
| **Admin** | All moderator permissions + manage rooms, members, and roles |

## Broadcasting

Bonfire dispatches events for real-time updates. Configure a broadcast driver (Reverb, Pusher, etc.) and set up Laravel Echo in your frontend to enable:

- Live message delivery
- Typing indicators
- Reaction updates
- Poll vote updates
- Call signaling

## License

MIT
