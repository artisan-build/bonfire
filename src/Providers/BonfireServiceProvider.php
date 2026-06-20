<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Providers;

use ArtisanBuild\Bonfire\BonfireManager;
use ArtisanBuild\Bonfire\Console\Commands\CreateRoomCommand;
use ArtisanBuild\Bonfire\Console\Commands\InstallCommand;
use ArtisanBuild\Bonfire\Models\Attachment;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Policies\AttachmentPolicy;
use ArtisanBuild\Bonfire\Policies\MemberPolicy;
use ArtisanBuild\Bonfire\Policies\MessagePolicy;
use ArtisanBuild\Bonfire\Policies\RoomPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Override;

class BonfireServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/bonfire.php', 'bonfire');

        $this->app->singleton(BonfireManager::class, fn (): BonfireManager => new BonfireManager);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');

        // Package models are not auto-discovered by Laravel's policy resolver,
        // so register them explicitly.
        Gate::policy(Room::class, RoomPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);
        Gate::policy(Member::class, MemberPolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);

        $this->publishes([
            __DIR__.'/../../config/bonfire.php' => config_path('bonfire.php'),
        ], 'bonfire-config');

        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'bonfire-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                CreateRoomCommand::class,
            ]);
        }
    }
}
