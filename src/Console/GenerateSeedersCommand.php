<?php

namespace Ridtichai\ExistingDbMigrationGenerator\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ridtichai\ExistingDbMigrationGenerator\Generators\SchemaReader;
use Ridtichai\ExistingDbMigrationGenerator\Generators\SeederWriter;

class GenerateSeedersCommand extends Command
{
    protected $signature = 'existing-db:generate-seeders
                            {--connection= : Database connection name}
                            {--path= : Output path}
                            {--table=* : Only generate specific tables}
                            {--ignore=* : Ignore specific tables}
                            {--chunk=500 : Number of rows per insert chunk}
                            {--all : Generate seeders for all tables}
                            {--force : Overwrite existing seeder files}
                            {--truncate : Use truncate() instead of delete()}
                            {--no-register : Do not auto register into DatabaseSeeder}';

    protected $description = 'Generate Laravel seeder files from existing database table data';

    /**
     * @param \Ridtichai\ExistingDbMigrationGenerator\Generators\SeederWriter $seederWriter
     * @param \Ridtichai\ExistingDbMigrationGenerator\Generators\SchemaReader $schemaReader
     * @return int
     */
    public function handle(SeederWriter $seederWriter, SchemaReader $schemaReader)
    {
        $connection = $this->option('connection') ?: config('database.default');
        $path = $this->option('path') ?: config('existing-db-migration-generator.default_seeder_output_path', 'database/seeders');
        $onlyTables = (array) $this->option('table');
        $ignoreTables = array_merge(
            config('existing-db-migration-generator.exclude_tables', []),
            (array) $this->option('ignore')
        );

        $chunkSize = (int) $this->option('chunk');
        $force = (bool) $this->option('force');
        $truncate = (bool) $this->option('truncate');
        $all = (bool) $this->option('all');
        $autoRegister = !$this->option('no-register') && (bool) config('existing-db-migration-generator.auto_register_seeder', true);
        $multiFileThreshold = (int) config('existing-db-migration-generator.multi_file_seed_threshold', 1000);

        if (!$all && empty($onlyTables)) {
            $this->error('Please specify at least one table using --table=table_name or use --all');
            return 1;
        }

        $tablesMeta = $schemaReader->read($connection, [], $ignoreTables);
        $sortedTablesMeta = $this->sortTablesByDependency($tablesMeta);

        $sortedTableNames = [];
        foreach ($sortedTablesMeta as $tableMeta) {
            $sortedTableNames[] = $tableMeta['name'];
        }

        if ($all) {
            $targetTables = $sortedTableNames;
        } else {
            $requested = array_values(array_unique($onlyTables));
            $targetTables = [];

            foreach ($sortedTableNames as $tableName) {
                if (in_array($tableName, $requested, true)) {
                    $targetTables[] = $tableName;
                }
            }

            foreach ($requested as $tableName) {
                if (!in_array($tableName, $targetTables, true)) {
                    $targetTables[] = $tableName;
                }
            }
        }

        $generatedClassNames = [];

        foreach ($targetTables as $table) {
            if (in_array($table, $ignoreTables, true)) {
                $this->warn("Skipping ignored table: {$table}");
                continue;
            }

            $this->info("Exporting data from table: {$table}");

            $rows = DB::connection($connection)->table($table)->get()->map(function ($item) {
                return (array) $item;
            })->toArray();

            if (empty($rows)) {
                $this->warn("No data found in table: {$table}");
            }

            $result = $seederWriter->write($table, $rows, $path, [
                'chunk_size' => $chunkSize,
                'force' => $force,
                'truncate' => $truncate,
                'auto_register_seeder' => false,
                'multi_file_threshold' => $multiFileThreshold,
            ]);

            foreach ($result['files'] as $filePath) {
                $this->info("Seeder generated: {$filePath}");
            }

            foreach ($result['class_names'] as $className) {
                $generatedClassNames[] = $className;
            }
        }

        if ($autoRegister && !empty($generatedClassNames)) {
            $seederWriter->registerSeederClasses($generatedClassNames, $path);
            $this->info('DatabaseSeeder updated successfully.');
        }

        return 0;
    }

    /**
     * @param array $tablesMeta
     * @return array
     */
    protected function sortTablesByDependency(array $tablesMeta)
    {
        $tableMap = [];
        foreach ($tablesMeta as $tableMeta) {
            $tableMap[$tableMeta['name']] = $tableMeta;
        }

        $dependencies = [];
        foreach ($tablesMeta as $tableMeta) {
            $tableName = $tableMeta['name'];
            $dependencies[$tableName] = [];

            foreach ($tableMeta['foreign_keys'] as $foreignKey) {
                $foreignTable = $foreignKey->getForeignTableName();

                if ($foreignTable !== $tableName && isset($tableMap[$foreignTable])) {
                    $dependencies[$tableName][] = $foreignTable;
                }
            }

            $dependencies[$tableName] = array_values(array_unique($dependencies[$tableName]));
        }

        $sorted = [];
        $visited = [];
        $visiting = [];

        foreach (array_keys($tableMap) as $tableName) {
            $this->visitTableDependency($tableName, $dependencies, $tableMap, $visited, $visiting, $sorted);
        }

        return $sorted;
    }

    /**
     * @param string $tableName
     * @param array $dependencies
     * @param array $tableMap
     * @param array $visited
     * @param array $visiting
     * @param array $sorted
     * @return void
     */
    protected function visitTableDependency($tableName, array $dependencies, array $tableMap, array &$visited, array &$visiting, array &$sorted)
    {
        if (isset($visited[$tableName])) {
            return;
        }

        if (isset($visiting[$tableName])) {
            return;
        }

        $visiting[$tableName] = true;

        foreach ($dependencies[$tableName] as $dependencyTable) {
            $this->visitTableDependency($dependencyTable, $dependencies, $tableMap, $visited, $visiting, $sorted);
        }

        unset($visiting[$tableName]);
        $visited[$tableName] = true;
        $sorted[] = $tableMap[$tableName];
    }
}