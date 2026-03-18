<?php

namespace Ridtichai\ExistingDbMigrationGenerator\Generators;

class TypeMapper
{
    /**
     * @param mixed $column
     * @param array $table
     * @param array $options
     * @return string
     */
    public function mapColumn($column, array $table, array $options = [])
    {
        $useLaravelStyleMacros = !empty($options['use_laravel_style_macros']);

        if ($useLaravelStyleMacros && $this->isLaravelIdColumn($column, $table)) {
            return '$table->id();';
        }

        if ($useLaravelStyleMacros) {
            $foreignKey = $this->findForeignKeyForColumn($column->getName(), $table);
            if ($foreignKey) {
                return $this->mapForeignIdColumn($column, $foreignKey);
            }
        }

        return $this->mapRegularColumn($column);
    }

    /**
     * @param mixed $column
     * @param array $table
     * @return bool
     */
    protected function isLaravelIdColumn($column, array $table)
    {
        $name = $column->getName();
        $type = strtolower($column->getType()->getName());

        if ($name !== 'id') {
            return false;
        }

        if (!method_exists($column, 'getAutoincrement') || !$column->getAutoincrement()) {
            return false;
        }

        if (!in_array($type, ['integer', 'int', 'bigint'], true)) {
            return false;
        }

        foreach ($table['indexes'] as $index) {
            if ($index->isPrimary() && $index->getColumns() === ['id']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $columnName
     * @param array $table
     * @return mixed|null
     */
    protected function findForeignKeyForColumn($columnName, array $table)
    {
        foreach ($table['foreign_keys'] as $foreignKey) {
            $localColumns = $foreignKey->getLocalColumns();

            if (count($localColumns) === 1 && $localColumns[0] === $columnName) {
                return $foreignKey;
            }
        }

        return null;
    }

    /**
     * @param mixed $column
     * @param mixed $foreignKey
     * @return string
     */
    protected function mapForeignIdColumn($column, $foreignKey)
    {
        $name = $column->getName();
        $type = strtolower($column->getType()->getName());
        $foreignTable = $foreignKey->getForeignTableName();
        $foreignColumns = $foreignKey->getForeignColumns();

        if (count($foreignColumns) === 1 && $foreignColumns[0] === 'id' && in_array($type, ['integer', 'int', 'bigint'], true)) {
            $line = "\$table->foreignId('{$name}')";

            if (!$column->getNotnull()) {
                $line .= '->nullable()';
            }

            $default = $column->getDefault();
            if ($default !== null) {
                $line .= $this->formatDefault($default);
            }

            $line .= "->constrained('{$foreignTable}')";

            return $line . ';';
        }

        return $this->mapRegularColumn($column);
    }

    /**
     * @param mixed $column
     * @return string
     */
    protected function mapRegularColumn($column)
    {
        $type = strtolower($column->getType()->getName());
        $name = $column->getName();

        switch ($type) {
            case 'bigint':
                $line = "\$table->bigInteger('{$name}')";
                break;

            case 'integer':
            case 'int':
                $line = "\$table->integer('{$name}')";
                break;

            case 'smallint':
                $line = "\$table->smallInteger('{$name}')";
                break;

            case 'boolean':
                $line = "\$table->boolean('{$name}')";
                break;

            case 'string':
                $length = $column->getLength() ?: 255;
                $line = "\$table->string('{$name}', {$length})";
                break;

            case 'text':
                $line = "\$table->text('{$name}')";
                break;

            case 'date':
                $line = "\$table->date('{$name}')";
                break;

            case 'datetime':
            case 'datetimetz':
                $line = "\$table->dateTime('{$name}')";
                break;

            case 'time':
                $line = "\$table->time('{$name}')";
                break;

            case 'decimal':
                $precision = $column->getPrecision() ?: 8;
                $scale = $column->getScale() ?: 2;
                $line = "\$table->decimal('{$name}', {$precision}, {$scale})";
                break;

            case 'float':
                $line = "\$table->float('{$name}')";
                break;

            case 'json':
                $line = "\$table->json('{$name}')";
                break;

            case 'blob':
                $line = "\$table->binary('{$name}')";
                break;

            default:
                $line = "// Unsupported column type: {$type} for {$name}";
                break;
        }

        if (strpos($line, '// Unsupported') === 0) {
            return $line;
        }

        if (!$column->getNotnull()) {
            $line .= '->nullable()';
        }

        $default = $column->getDefault();
        if ($default !== null) {
            $line .= $this->formatDefault($default);
        }

        return $line . ';';
    }

    /**
     * @param mixed $default
     * @return string
     */
    protected function formatDefault($default)
    {
        if (is_numeric($default)) {
            return "->default({$default})";
        }

        $defaultString = (string) $default;
        $escaped = str_replace("'", "\\'", $defaultString);

        return "->default('{$escaped}')";
    }
}