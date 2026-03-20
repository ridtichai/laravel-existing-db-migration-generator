<?php

namespace Ridtichai\ExistingDbMigrationGenerator\Generators;

use Doctrine\DBAL\Types\Type;
use Illuminate\Support\Str;
use Ridtichai\ExistingDbMigrationGenerator\Support\CrudCommentParser;

class CrudFieldResolver
{
    /**
     * @var \Ridtichai\ExistingDbMigrationGenerator\Support\CrudCommentParser
     */
    protected $commentParser;

    /**
     * @param \Ridtichai\ExistingDbMigrationGenerator\Support\CrudCommentParser $commentParser
     */
    public function __construct(CrudCommentParser $commentParser)
    {
        $this->commentParser = $commentParser;
    }

    /**
     * @param array $tableMeta
     * @return array
     */
    public function resolve(array $tableMeta)
    {
        $fields = [];
        $foreignKeyMap = $this->buildForeignKeyMap($tableMeta);

        foreach ($tableMeta['columns'] as $column) {
            $name = $column->getName();

            if ($this->shouldSkipColumn($name)) {
                continue;
            }

            $isForeign = isset($foreignKeyMap[$name]);
            $columnType = $this->getColumnTypeName($column);
            $fallbackLabel = $this->makeDefaultLabel($name);
            $fallbackInputType = $this->guessInputType($name, $columnType, $isForeign);

            $parsed = $this->commentParser->parse(
                method_exists($column, 'getComment') ? $column->getComment() : '',
                $fallbackLabel,
                $fallbackInputType
            );

            $inputType = $parsed['input_type'];

            if ($name === 'password') {
                $inputType = 'password';
            }

            if ($isForeign && $inputType === 'text') {
                $inputType = 'select';
            }

            $relatedTable = $isForeign ? $foreignKeyMap[$name]['table'] : null;
            $relatedModel = $relatedTable ? Str::studly(Str::singular($relatedTable)) : null;
            $relatedVariable = $relatedModel ? Str::camel(Str::plural($relatedModel)) : null;

            $fields[] = [
                'name' => $name,
                'label' => $parsed['label'],
                'input_type' => $inputType,
                'column_type' => $columnType,
                'nullable' => !$column->getNotnull(),
                'required' => $column->getNotnull(),
                'length' => method_exists($column, 'getLength') ? $column->getLength() : null,
                'precision' => method_exists($column, 'getPrecision') ? $column->getPrecision() : null,
                'scale' => method_exists($column, 'getScale') ? $column->getScale() : null,
                'default' => method_exists($column, 'getDefault') ? $column->getDefault() : null,
                'is_foreign' => $isForeign,
                'related_table' => $relatedTable,
                'related_model' => $relatedModel,
                'related_variable' => $relatedVariable,
                'form_visible' => $inputType !== 'hidden',
                'index_visible' => !in_array($inputType, ['password', 'hidden'], true),
            ];
        }

        return $fields;
    }

    /**
     * @param array $tableMeta
     * @return array
     */
    protected function buildForeignKeyMap(array $tableMeta)
    {
        $map = [];

        foreach ($tableMeta['foreign_keys'] as $foreignKey) {
            $localColumns = $foreignKey->getLocalColumns();
            $foreignColumns = $foreignKey->getForeignColumns();

            if (count($localColumns) === 1 && count($foreignColumns) === 1) {
                $map[$localColumns[0]] = [
                    'table' => $foreignKey->getForeignTableName(),
                    'column' => $foreignColumns[0],
                ];
            }
        }

        return $map;
    }

    /**
     * @param string $name
     * @return bool
     */
    protected function shouldSkipColumn($name)
    {
        return in_array($name, [
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
            'remember_token',
        ], true);
    }

    /**
     * @param string $name
     * @param string $columnType
     * @param bool $isForeign
     * @return string
     */
    protected function guessInputType($name, $columnType, $isForeign)
    {
        if ($isForeign) {
            return 'select';
        }

        if ($name === 'password') {
            return 'password';
        }

        switch ($columnType) {
            case 'text':
                return 'textarea';

            case 'smallint':
            case 'integer':
            case 'int':
            case 'bigint':
            case 'decimal':
            case 'float':
                return 'number';

            case 'date':
                return 'date';

            case 'datetime':
            case 'datetimetz':
            case 'timestamp':
            case 'timestamptz':
                return 'datetime-local';

            case 'time':
            case 'timetz':
                return 'time';

            case 'boolean':
                return 'checkbox';

            default:
                return 'text';
        }
    }

    /**
     * @param string $name
     * @return string
     */
    protected function makeDefaultLabel($name)
    {
        return Str::title(str_replace('_', ' ', $name));
    }

    /**
     * @param mixed $column
     * @return string
     */
    protected function getColumnTypeName($column)
    {
        $typeObject = $column->getType();

        if (method_exists($typeObject, 'getName')) {
            return strtolower($typeObject->getName());
        }

        return strtolower(Type::getTypeRegistry()->lookupName($typeObject));
    }
}