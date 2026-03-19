<?php

namespace Ridtichai\ExistingDbMigrationGenerator\Generators;

use Doctrine\DBAL\DriverManager;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SchemaReader
{
    /**
     * @param string $connectionName
     * @param array $onlyTables
     * @param array $ignoreTables
     * @return array
     */
    public function read($connectionName, array $onlyTables = [], array $ignoreTables = [])
    {
        $connection = DB::connection($connectionName);
        $config = $connection->getConfig();

        $schemaManager = $this->createDoctrineSchemaManager($config);

        if (!method_exists($schemaManager, 'listTableNames')) {
            throw new RuntimeException('Unable to list database tables.');
        }

        $tableNames = $schemaManager->listTableNames();
        $result = [];

        foreach ($tableNames as $tableName) {
            if (!empty($onlyTables) && !in_array($tableName, $onlyTables, true)) {
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

    /**
     * @param array $config
     * @return mixed
     */
    protected function createDoctrineSchemaManager(array $config)
    {
        $params = $this->buildDoctrineConnectionParams($config);

        if (method_exists(DriverManager::class, 'getConnection')) {
            $doctrineConnection = DriverManager::getConnection($params);
        } else {
            $doctrineConnection = DriverManager::createConnection($params);
        }

        if (!method_exists($doctrineConnection, 'createSchemaManager')) {
            throw new RuntimeException('Doctrine DBAL createSchemaManager() is not available.');
        }

        return $doctrineConnection->createSchemaManager();
    }

    /**
     * @param array $config
     * @return array
     */
    protected function buildDoctrineConnectionParams(array $config)
    {
        $driver = isset($config['driver']) ? $config['driver'] : null;

        switch ($driver) {
            case 'mysql':
            case 'mariadb':
                return [
                    'driver' => 'pdo_mysql',
                    'host' => isset($config['host']) ? $config['host'] : '127.0.0.1',
                    'port' => isset($config['port']) ? (int) $config['port'] : 3306,
                    'dbname' => isset($config['database']) ? $config['database'] : null,
                    'user' => isset($config['username']) ? $config['username'] : null,
                    'password' => isset($config['password']) ? $config['password'] : null,
                    'unix_socket' => isset($config['unix_socket']) ? $config['unix_socket'] : null,
                    'charset' => isset($config['charset']) ? $config['charset'] : 'utf8mb4',
                ];

            case 'pgsql':
                return [
                    'driver' => 'pdo_pgsql',
                    'host' => isset($config['host']) ? $config['host'] : '127.0.0.1',
                    'port' => isset($config['port']) ? (int) $config['port'] : 5432,
                    'dbname' => isset($config['database']) ? $config['database'] : null,
                    'user' => isset($config['username']) ? $config['username'] : null,
                    'password' => isset($config['password']) ? $config['password'] : null,
                    'charset' => isset($config['charset']) ? $config['charset'] : 'utf8',
                ];

            case 'sqlite':
                return [
                    'driver' => 'pdo_sqlite',
                    'path' => isset($config['database']) ? $config['database'] : null,
                ];

            case 'sqlsrv':
                return [
                    'driver' => 'pdo_sqlsrv',
                    'host' => isset($config['host']) ? $config['host'] : '127.0.0.1',
                    'port' => isset($config['port']) ? (int) $config['port'] : 1433,
                    'dbname' => isset($config['database']) ? $config['database'] : null,
                    'user' => isset($config['username']) ? $config['username'] : null,
                    'password' => isset($config['password']) ? $config['password'] : null,
                ];
        }

        throw new RuntimeException('Unsupported database driver: ' . $driver);
    }
}