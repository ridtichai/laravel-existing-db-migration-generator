<?php

namespace Ridtichai\ExistingDbMigrationGenerator\Generators;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class SchemaReader
{
    public function read($connectionName, array $onlyTables = [], array $ignoreTables = [])
    {
        $connection = DB::connection($connectionName);
        $doctrineConnection = $connection->getDoctrineConnection();

        if (method_exists($doctrineConnection, 'createSchemaManager')) {
            $schemaManager = $doctrineConnection->createSchemaManager();
        } else {
            $schemaManager = $doctrineConnection->getSchemaManager();
        }

        if (! method_exists($schemaManager, 'listTableNames')) {
            throw new RuntimeException('Unable to list database tables.');
        }

        $tableNames = $schemaManager->listTableNames();
        $result = [];

        foreach ($tableNames as $tableName) {
            if (! empty($onlyTables) && ! in_array($tableName, $onlyTables, true)) {
                continue;
            }

            if (in_array($tableName, $ignoreTables, true)) {
                continue;
            }

            $columns = $schemaManager->listTableColumns($tableName);
            $indexes = method_exists($schemaManager, 'listTableIndexes')
                ? $schemaManager->listTableIndexes($tableName)
                : [];
            $foreignKeys = method_exists($schemaManager, 'listTableForeignKeys')
                ? $schemaManager->listTableForeignKeys($tableName)
                : [];

            $result[] = [
                'name' => $tableName,
                'columns' => $columns,
                'indexes' => $indexes,
                'foreign_keys' => $foreignKeys,
            ];
        }

        return $result;
    }
}