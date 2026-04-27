<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Providers;

use ArtisanBuild\Bonfire\BonfireManager;
use ArtisanBuild\Bonfire\Console\Commands\CreateRoomCommand;
use ArtisanBuild\Bonfire\Console\Commands\InstallCommand;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
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
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'bonfire');
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');

        Livewire::addNamespace(
            namespace: 'bonfire',
            viewPath: __DIR__.'/../../resources/views/components',
        );

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
