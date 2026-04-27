<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Facades\Bonfire;
use Illuminate\Support\Facades\Auth;

return [
    'tenant_id' => fn () => null,

    'disk' => 'public',
    'max_attachment_size_kb' => 10240,
    'allowed_attachment_types' => ['image/*', 'application/pdf'],

    'route_prefix' => 'bonfire',
    'route_middleware' => ['web', 'auth'],

    'notification_channels' => ['database'],

    'user_profile_url' => fn ($member) => null,

    'link_preview_enabled' => true,
    'link_preview_timeout_seconds' => 5,

    'resolve_member' => fn () => Bonfire::memberFor(Auth::user()),
];
