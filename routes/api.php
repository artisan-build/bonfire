<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Http\Controllers\Api\V1\MessageController;
use ArtisanBuild\Bonfire\Http\Controllers\Api\V1\RoomController;
use ArtisanBuild\Bonfire\Http\Controllers\Api\V1\RoomMemberController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

$prefix = config('bonfire.api.prefix', 'bonfire/api');
$middleware = config('bonfire.api.middleware', ['auth:sanctum']);

// The host owns the auth guard via config; the package guarantees route-model
// binding regardless of the host's middleware choice. SubstituteBindings is
// idempotent (already-resolved parameters are skipped) so appending it is safe
// even when the host's middleware stack already includes it.
$middleware[] = SubstituteBindings::class;

Route::prefix($prefix)
    ->middleware($middleware)
    ->name('bonfire.api.')
    ->group(function (): void {
        // Rooms the authed member belongs to, and room detail.
        Route::get('rooms', [RoomController::class, 'index'])->name('rooms.index');
        Route::get('rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');

        // Messages within a room.
        Route::get('rooms/{room}/messages', [MessageController::class, 'index'])->name('rooms.messages.index');
        Route::post('rooms/{room}/messages', [MessageController::class, 'store'])->name('rooms.messages.store');

        // Single-message writes.
        Route::patch('messages/{message}', [MessageController::class, 'update'])->name('messages.update');
        Route::delete('messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');
        Route::post('messages/{message}/reactions', [MessageController::class, 'react'])->name('messages.reactions.store');
        Route::post('messages/{message}/poll/vote', [MessageController::class, 'vote'])->name('messages.poll.vote');

        // Room read state.
        Route::post('rooms/{room}/read', [RoomController::class, 'read'])->name('rooms.read');

        // Roster management (admin only via RoomPolicy@manage).
        Route::post('rooms/{room}/members', [RoomMemberController::class, 'store'])->name('rooms.members.store');
        Route::delete('rooms/{room}/members/{member}', [RoomMemberController::class, 'destroy'])->name('rooms.members.destroy');
    });
