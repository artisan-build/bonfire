<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Console\Commands;

use Illuminate\Console\Command;

/**
 * Publishes Bonfire config and migrations, then runs migrate.
 */
class InstallCommand extends Command
{
    protected $signature = 'bonfire:install {--force : Overwrite any existing published files}';

    protected $description = 'Publish Bonfire config and migrations, then run migrations.';

    public function handle(): int
    {
        $this->components->info('Publishing Bonfire config…');
        $this->call('vendor:publish', [
            '--tag' => 'bonfire-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->components->info('Publishing Bonfire migrations…');
        $this->call('vendor:publish', [
            '--tag' => 'bonfire-migrations',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->components->info('Running migrations…');
        $this->call('migrate');

        $this->components->success('Bonfire installed.');

        return self::SUCCESS;
    }
}
