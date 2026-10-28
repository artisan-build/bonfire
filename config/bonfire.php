<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Facades\Bonfire;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Auth;

return [
    'disk' => 'public',
    'max_attachment_size_kb' => 10240,
    // Concrete server-side MIME allowlist enforced via the `mimetypes:` rule.
    // SVG (image/svg+xml) is intentionally excluded — it can carry script and
    // is an XSS vector — so the previous `image/*` wildcard was narrowed to
    // explicit raster types plus PDF.
    'allowed_attachment_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'],

    'route_prefix' => 'bonfire',
    'route_middleware' => ['web', 'auth'],

    // HTTP/JSON API v1. Opt-in: the host enables it and owns the auth guard
    // wiring (Sanctum cookie for SPAs, personal access tokens for native apps).
    // The package never adds laravel/sanctum to its runtime require — the host
    // installs and configures the guard; the middleware below is fully
    // host-overridable so the auth stack is a Cloud/host concern.
    'api' => [
        'enabled' => Env::get('BONFIRE_API_ENABLED', false),
        'prefix' => 'bonfire/api',
        'middleware' => ['auth:sanctum'],
    ],

    'notification_channels' => ['database'],

    'bulk_mentions' => true,

    'user_profile_url' => fn ($member) => null,

    'link_preview_enabled' => true,
    'link_preview_timeout_seconds' => 5,

    'resolve_member' => fn () => Bonfire::memberFor(Auth::user()),

    // Formats the broadcast channel name for a host user identifier. The default
    // matches Laravel's `App.Models.User.{id}` convention so the host's existing
    // channel authorization keeps working unchanged. Override to namespace
    // channels per tenant, e.g. fn ($id) => 'tenant.'.$id.
    'user_channel' => fn ($id) => 'App.Models.User.'.$id,
];
