<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Http\Controllers\AttachmentController;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Support\Facades\Route;

$prefix = config('bonfire.route_prefix', 'bonfire');
$middleware = config('bonfire.route_middleware', ['web']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () {
        Route::get('/', fn () => view('bonfire::pages.index'))->name('bonfire.index');

        Route::get('admin', fn () => view('bonfire::pages.admin'))->name('bonfire.admin.index');

        Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])
            ->name('bonfire.attachments.show');

        Route::get('{room:slug}', fn (Room $room) => view('bonfire::pages.room', ['room' => $room]))
            ->name('bonfire.room.show');
    });
