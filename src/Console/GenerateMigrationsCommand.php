<?php

namespace Ridtichai\ExistingDbMigrationGenerator\Console;

use Illuminate\Console\Command;
use Ridtichai\ExistingDbMigrationGenerator\Generators\MigrationWriter;
use Ridtichai\ExistingDbMigrationGenerator\Generators\SchemaReader;

class GenerateMigrationsCommand extends Command
{
    protected $signature = 'existing-db:generate-migrations
                            {--connection= : Database connection name}
                            {--path= : Output path}
                            {--table=* : Only generate specific tables}
                            {--ignore=* : Ignore specific tables}';

    protected $description = 'Generate Laravel migration files from an existing database schema';

    public function handle(SchemaReader $schemaReader, MigrationWriter $migrationWriter)
    {
        $connection = $this->option('connection') ?: config('database.default');
        $path = $this->option('path') ?: config('existing-db-migration-generator.default_output_path');
        $onlyTables = (array) $this->option('table');
        $ignoreTables = array_merge(
            config('existing-db-migration-generator.exclude_tables', []),
            (array) $this->option('ignore')
        );

        $useLaravelStyleMacros = (bool) config('existing-db-migration-generator.use_laravel_style_macros', true);

        $this->info('Reading schema from connection: ' . $connection);

        $tables = $schemaReader->read($connection, $onlyTables, $ignoreTables);

        if (empty($tables)) {
            $this->warn('No tables found.');
            return 0;
        }

        $migrationWriter->write($tables, $path, [
            'use_laravel_style_macros' => $useLaravelStyleMacros,
        ]);

        $this->info('Migration files generated successfully.');
        return 0;
    }
}