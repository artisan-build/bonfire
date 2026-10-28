<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Tests;

use ArtisanBuild\Bonfire\Providers\BonfireServiceProvider;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Schema::create('test_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('test_bots', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    protected function getPackageProviders($app): array
    {
        return [
            BonfireServiceProvider::class,
            // Dev-only: the package never requires Sanctum at runtime (the host
            // owns the guard); the test suite registers it so the config-driven
            // `auth:sanctum` API stack can be exercised end to end.
            SanctumServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('bonfire.route_middleware', ['web']);

        // Enable and wire the HTTP/JSON API v1 for the test environment: a
        // Sanctum guard backed by the TestUser fixture so `Sanctum::actingAs()`
        // authenticates, and the real `auth:sanctum` middleware on the routes.
        $app['config']->set('auth.providers.users.model', TestUser::class);
        $app['config']->set('auth.guards.sanctum', [
            'driver' => 'sanctum',
            'provider' => 'users',
        ]);
        $app['config']->set('bonfire.api.enabled', true);
        $app['config']->set('bonfire.api.middleware', ['auth:sanctum']);

        // The message-created hook can dispatch a link-preview fetch; keep the
        // suite free of any outbound network work.
        $app['config']->set('bonfire.link_preview_enabled', false);
    }
}
