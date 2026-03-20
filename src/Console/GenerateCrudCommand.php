<?php

namespace Ridtichai\ExistingDbMigrationGenerator\Console;

use Illuminate\Console\Command;
use Ridtichai\ExistingDbMigrationGenerator\Generators\CrudGenerator;
use Ridtichai\ExistingDbMigrationGenerator\Generators\SchemaReader;

class GenerateCrudCommand extends Command
{
    protected $signature = 'existing-db:generate-crud
                            {--connection= : Database connection name}
                            {--table= : Table name}
                            {--force : Overwrite existing files}';

    protected $description = 'Generate CRUD controller and blade views from an existing database table';

    /**
     * @param \Ridtichai\ExistingDbMigrationGenerator\Generators\SchemaReader $schemaReader
     * @param \Ridtichai\ExistingDbMigrationGenerator\Generators\CrudGenerator $crudGenerator
     * @return int
     */
    public function handle(SchemaReader $schemaReader, CrudGenerator $crudGenerator)
    {
        $connection = $this->option('connection') ?: config('database.default');
        $table = $this->option('table');
        $force = (bool) $this->option('force');

        if (!$table) {
            $this->error('Please specify table name using --table=table_name');
            return 1;
        }

        $tables = $schemaReader->read($connection, [$table], []);

        if (empty($tables)) {
            $this->error("Table not found: {$table}");
            return 1;
        }

        $result = $crudGenerator->generate($tables[0], [
            'force' => $force,
        ]);

        foreach ($result['files'] as $file) {
            $this->info('Generated: ' . $file);
        }

        $this->line('');
        $this->warn('Remember to create or verify the Eloquent model and routes manually if needed.');
        $this->line('Example route: Route::resource(\'' . $table . '\', ' . $result['controller_class'] . '::class);');

        return 0;
    }
}