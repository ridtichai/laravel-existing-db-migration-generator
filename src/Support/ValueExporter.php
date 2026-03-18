<?php

namespace Ridtichai\ExistingDbMigrationGenerator\Support;

class ValueExporter
{
    /**
     * @param mixed $value
     * @param int $indent
     * @return string
     */
    public function export($value, $indent = 0)
    {
        if (is_array($value)) {
            return $this->exportArray($value, $indent);
        }

        return $this->exportScalar($value);
    }

    /**
     * @param array $array
     * @param int $indent
     * @return string
     */
    protected function exportArray(array $array, $indent)
    {
        if (empty($array)) {
            return '[]';
        }

        $spaces = str_repeat(' ', $indent);
        $childSpaces = str_repeat(' ', $indent + 4);
        $lines = [];
        $isList = $this->isList($array);

        foreach ($array as $key => $value) {
            $exportedValue = $this->export($value, $indent + 4);

            if ($isList) {
                $lines[] = $childSpaces . $exportedValue . ',';
            } else {
                $exportedKey = $this->exportArrayKey($key);
                $lines[] = $childSpaces . $exportedKey . ' => ' . $exportedValue . ',';
            }
        }

        return "[\n" . implode("\n", $lines) . "\n" . $spaces . "]";
    }

    /**
     * @param mixed $value
     * @return string
     */
    protected function exportScalar($value)
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return var_export($value, true);
        }

        if (is_string($value)) {
            return "'" . str_replace(
                ["\\", "'"],
                ["\\\\", "\\'"],
                $value
            ) . "'";
        }

        return var_export($value, true);
    }

    /**
     * @param mixed $key
     * @return string
     */
    protected function exportArrayKey($key)
    {
        if (is_int($key)) {
            return (string) $key;
        }

        return "'" . str_replace(
            ["\\", "'"],
            ["\\\\", "\\'"],
            (string) $key
        ) . "'";
    }

    /**
     * @param array $array
     * @return bool
     */
    protected function isList(array $array)
    {
        if (function_exists('array_is_list')) {
            return array_is_list($array);
        }

        $expectedKey = 0;

        foreach ($array as $key => $value) {
            if ($key !== $expectedKey) {
                return false;
            }

            $expectedKey++;
        }

        return true;
    }
}