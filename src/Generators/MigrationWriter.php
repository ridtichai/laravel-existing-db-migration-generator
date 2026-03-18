<?php

namespace Ridtichai\ExistingDbMigrationGenerator\Generators;

use Illuminate\Support\Str;

class MigrationWriter
{
    /**
     * @var \Ridtichai\ExistingDbMigrationGenerator\Generators\TypeMapper
     */
    protected $typeMapper;

    /**
     * @param \Ridtichai\ExistingDbMigrationGenerator\Generators\TypeMapper $typeMapper
     */
    public function __construct(TypeMapper $typeMapper)
    {
        $this->typeMapper = $typeMapper;
    }

    /**
     * @param array $tables
     * @param string $path
     * @param array $options
     * @return void
     */
    public function write(array $tables, $path, array $options = [])
    {
        $fullPath = $this->resolveOutputPath($path);

        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        $timestamp = time();

        foreach ($tables as $index => $table) {
            $className = 'Create' . Str::studly($table['name']) . 'Table';
            $fileName = date('Y_m_d_His', $timestamp + $index) . '_create_' . $table['name'] . '_table.php';

            $content = $this->buildMigrationContent($className, $table, $options);

            file_put_contents($fullPath . DIRECTORY_SEPARATOR . $fileName, $content);
        }
    }

    /**
     * @param string $path
     * @return string
     */
    protected function resolveOutputPath($path)
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        if (function_exists('base_path')) {
            return base_path($path);
        }

        return $path;
    }

    /**
     * @param string $path
     * @return bool
     */
    protected function isAbsolutePath($path)
    {
        if (!$path) {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:\\\\/', $path);
    }

    /**
     * @param string $className
     * @param array $table
     * @param array $options
     * @return string
     */
    protected function buildMigrationContent($className, array $table, array $options = [])
    {
        $lines = [];
        $useLaravelStyleMacros = !empty($options['use_laravel_style_macros']);
        $useTimestamps = $useLaravelStyleMacros && $this->hasLaravelTimestamps($table['columns']);

        foreach ($table['columns'] as $column) {
            if ($useTimestamps && in_array($column->getName(), ['created_at', 'updated_at'], true)) {
                continue;
            }

            $lines[] = '            ' . $this->typeMapper->mapColumn($column, $table, $options);
        }

        if ($useTimestamps) {
            $lines[] = '            $table->timestamps();';
        }

        $upBody = implode("\n", $lines);
        $tableName = $table['name'];

        return <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

class {$className} extends Migration
{
    public function up()
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
{$upBody}
        });
    }

    public function down()
    {
        Schema::dropIfExists('{$tableName}');
    }
}

PHP;
    }

    /**
     * @param array $columns
     * @return bool
     */
    protected function hasLaravelTimestamps(array $columns)
    {
        $hasCreatedAt = false;
        $hasUpdatedAt = false;

        foreach ($columns as $column) {
            $name = $column->getName();
            $type = strtolower($column->getType()->getName());

            if ($name === 'created_at' && in_array($type, ['datetime', 'datetimetz'], true)) {
                $hasCreatedAt = true;
            }

            if ($name === 'updated_at' && in_array($type, ['datetime', 'datetimetz'], true)) {
                $hasUpdatedAt = true;
            }
        }

        return $hasCreatedAt && $hasUpdatedAt;
    }
}