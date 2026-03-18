<?php

namespace Ridtichai\ExistingDbMigrationGenerator\Generators;

use Illuminate\Support\Str;

class MigrationWriter
{
    protected $typeMapper;

    public function __construct(TypeMapper $typeMapper)
    {
        $this->typeMapper = $typeMapper;
    }

    public function write(array $tables, $path)
    {
        $fullPath = base_path($path);

        if (! is_dir($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        $timestamp = time();

        foreach ($tables as $index => $table) {
            $className = 'Create' . Str::studly($table['name']) . 'Table';
            $fileName = date('Y_m_d_His', $timestamp + $index) . '_create_' . $table['name'] . '_table.php';

            $content = $this->buildMigrationContent($className, $table);

            file_put_contents($fullPath . DIRECTORY_SEPARATOR . $fileName, $content);
        }
    }

    protected function buildMigrationContent($className, array $table)
    {
        $lines = [];

        foreach ($table['columns'] as $column) {
            $lines[] = '            ' . $this->typeMapper->mapColumn($column);
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
}