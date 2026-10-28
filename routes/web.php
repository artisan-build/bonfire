<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Http\Controllers\AttachmentController;
use Illuminate\Support\Facades\Route;

$prefix = config('bonfire.route_prefix', 'bonfire');
$middleware = config('bonfire.route_middleware', ['web']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () {
        Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])
            ->name('bonfire.attachments.show');
    });
