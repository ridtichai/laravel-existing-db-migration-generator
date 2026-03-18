<?php

namespace Ridtichai\ExistingDbMigrationGenerator\Generators;

use Illuminate\Support\Str;
use Ridtichai\ExistingDbMigrationGenerator\Support\ValueExporter;
use RuntimeException;

class SeederWriter
{
    /**
     * @var \Ridtichai\ExistingDbMigrationGenerator\Support\ValueExporter
     */
    protected $valueExporter;

    /**
     * @param \Ridtichai\ExistingDbMigrationGenerator\Support\ValueExporter $valueExporter
     */
    public function __construct(ValueExporter $valueExporter)
    {
        $this->valueExporter = $valueExporter;
    }

    /**
     * @param string $table
     * @param array $rows
     * @param string $path
     * @param array $options
     * @return array
     */
    public function write($table, array $rows, $path, array $options = [])
    {
        $fullPath = $this->resolveOutputPath($path);

        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        $chunkSize = !empty($options['chunk_size']) ? (int) $options['chunk_size'] : 500;
        $force = !empty($options['force']);
        $truncate = !empty($options['truncate']);
        $autoRegister = !empty($options['auto_register_seeder']);
        $multiFileThreshold = !empty($options['multi_file_threshold']) ? (int) $options['multi_file_threshold'] : 1000;

        $result = $this->writeSeederFiles($table, $rows, $fullPath, $chunkSize, $force, $truncate, $multiFileThreshold);

        if ($autoRegister && !empty($result['class_names'])) {
            $this->registerSeederClasses($result['class_names'], $path);
        }

        return $result;
    }

    /**
     * @param array $classNames
     * @param string $path
     * @return void
     */
    public function registerSeederClasses(array $classNames, $path)
    {
        $classNames = array_values(array_unique($classNames));

        if (empty($classNames)) {
            return;
        }

        $databaseSeederPath = $this->ensureDatabaseSeederExists($path);
        $content = file_get_contents($databaseSeederPath);

        $existingCalls = $this->extractExistingSeederCalls($content);
        $finalCalls = $existingCalls;

        foreach ($classNames as $className) {
            if (!in_array($className, $finalCalls, true)) {
                $finalCalls[] = $className;
            }
        }

        $newContent = $this->buildDatabaseSeederContent($finalCalls);
        file_put_contents($databaseSeederPath, $newContent);
    }

    /**
     * @param string $table
     * @param array $rows
     * @param string $fullPath
     * @param int $chunkSize
     * @param bool $force
     * @param bool $truncate
     * @param int $multiFileThreshold
     * @return array
     */
    protected function writeSeederFiles($table, array $rows, $fullPath, $chunkSize, $force, $truncate, $multiFileThreshold)
    {
        $baseClassName = Str::studly($table) . 'TableSeeder';
        $files = [];
        $classNames = [];

        $shouldSplitToMultipleFiles = $multiFileThreshold > 0 && count($rows) > $multiFileThreshold;

        if (!$shouldSplitToMultipleFiles) {
            $fileName = $baseClassName . '.php';
            $filePath = $fullPath . DIRECTORY_SEPARATOR . $fileName;

            if (file_exists($filePath) && !$force) {
                throw new RuntimeException("Seeder file already exists: {$filePath}. Use --force to overwrite.");
            }

            $content = $this->buildSeederContent($table, $baseClassName, $rows, $chunkSize, $truncate);
            file_put_contents($filePath, $content);

            $files[] = $filePath;
            $classNames[] = $baseClassName;

            return [
                'files' => $files,
                'class_names' => $classNames,
            ];
        }

        $rowFileChunks = array_chunk($rows, $multiFileThreshold);

        foreach ($rowFileChunks as $index => $rowChunk) {
            $className = $baseClassName . 'Part' . ($index + 1);
            $fileName = $className . '.php';
            $filePath = $fullPath . DIRECTORY_SEPARATOR . $fileName;

            if (file_exists($filePath) && !$force) {
                throw new RuntimeException("Seeder file already exists: {$filePath}. Use --force to overwrite.");
            }

            $content = $this->buildSeederContent($table, $className, $rowChunk, $chunkSize, $truncate && $index === 0);
            file_put_contents($filePath, $content);

            $files[] = $filePath;
            $classNames[] = $className;
        }

        return [
            'files' => $files,
            'class_names' => $classNames,
        ];
    }

    /**
     * @param string $table
     * @param string $className
     * @param array $rows
     * @param int $chunkSize
     * @param bool $truncate
     * @return string
     */
    protected function buildSeederContent($table, $className, array $rows, $chunkSize, $truncate)
    {
        $resetStatement = $truncate
            ? "DB::table('{$table}')->truncate();"
            : "DB::table('{$table}')->delete();";

        $insertBlocks = $this->buildInsertBlocks($table, $rows, $chunkSize);

        return <<<PHP
<?php

namespace Database\\Seeders;

use Illuminate\\Database\\Seeder;
use Illuminate\\Support\\Facades\\DB;

class {$className} extends Seeder
{
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        {$resetStatement}

{$insertBlocks}
    }
}

PHP;
    }

    /**
     * @param string $table
     * @param array $rows
     * @param int $chunkSize
     * @return string
     */
    protected function buildInsertBlocks($table, array $rows, $chunkSize)
    {
        if (empty($rows)) {
            return "        // No data found.\n";
        }

        $chunks = array_chunk($rows, $chunkSize);
        $blocks = [];

        foreach ($chunks as $chunk) {
            $exported = $this->valueExporter->export($chunk, 12);
            $blocks[] = "        DB::table('{$table}')->insert({$exported});";
        }

        return implode("\n\n", $blocks) . "\n";
    }

    /**
     * @param string $path
     * @return string
     */
    protected function ensureDatabaseSeederExists($path)
    {
        $fullPath = $this->resolveOutputPath($path);

        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        $databaseSeederPath = $fullPath . DIRECTORY_SEPARATOR . 'DatabaseSeeder.php';

        if (!file_exists($databaseSeederPath)) {
            file_put_contents($databaseSeederPath, $this->buildDatabaseSeederContent([]));
        }

        return $databaseSeederPath;
    }

    /**
     * @param string $content
     * @return array
     */
    protected function extractExistingSeederCalls($content)
    {
        $matches = [];
        preg_match_all('/\$this->call\(\s*([A-Za-z0-9_\\\\]+)::class\s*\)\s*;/', $content, $matches);

        if (empty($matches[1])) {
            return [];
        }

        $classNames = [];
        foreach ($matches[1] as $className) {
            if (strpos($className, '\\') !== false) {
                $parts = explode('\\', $className);
                $className = end($parts);
            }

            $classNames[] = $className;
        }

        return array_values(array_unique($classNames));
    }

    /**
     * @param array $classNames
     * @return string
     */
    protected function buildDatabaseSeederContent(array $classNames)
    {
        $callLines = [];

        foreach ($classNames as $className) {
            $callLines[] = "        \$this->call({$className}::class);";
        }

        $calls = empty($callLines)
            ? "        //\n"
            : implode("\n", $callLines);

        return <<<PHP
<?php

namespace Database\\Seeders;

use Illuminate\\Database\\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
{$calls}
    }
}

PHP;
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
}