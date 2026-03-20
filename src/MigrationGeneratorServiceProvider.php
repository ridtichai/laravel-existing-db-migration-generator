<?php

namespace Ridtichai\ExistingDbMigrationGenerator;

use Illuminate\Support\ServiceProvider;
use Ridtichai\ExistingDbMigrationGenerator\Console\GenerateMigrationsCommand;
use Ridtichai\ExistingDbMigrationGenerator\Console\GenerateSeedersCommand;

class MigrationGeneratorServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/migration-generator.php',
            'existing-db-migration-generator'
        );
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/migration-generator.php' => config_path('existing-db-migration-generator.php'),
        ], 'existing-db-migration-generator-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateMigrationsCommand::class,
                GenerateSeedersCommand::class,
                GenerateCrudCommand::class,
            ]);
        }
    }
}