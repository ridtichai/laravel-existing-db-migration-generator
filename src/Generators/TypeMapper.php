<?php

namespace Ridtichai\ExistingDbMigrationGenerator\Generators;

class TypeMapper
{
    public function mapColumn($column)
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

        if (! $column->getNotnull()) {
            $line .= '->nullable()';
        }

        $default = $column->getDefault();
        if ($default !== null) {
            if (is_numeric($default)) {
                $line .= "->default({$default})";
            } else {
                $escaped = str_replace("'", "\\'", (string) $default);
                $line .= "->default('{$escaped}')";
            }
        }

        $line .= ';';

        return $line;
    }
}