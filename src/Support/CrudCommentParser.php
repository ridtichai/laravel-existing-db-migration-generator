<?php

namespace Ridtichai\ExistingDbMigrationGenerator\Support;

class CrudCommentParser
{
    /**
     * @param string|null $comment
     * @param string $fallbackLabel
     * @param string $fallbackInputType
     * @return array
     */
    public function parse($comment, $fallbackLabel, $fallbackInputType = 'text')
    {
        $comment = trim((string) $comment);

        if ($comment === '') {
            return [
                'label' => $fallbackLabel,
                'input_type' => $fallbackInputType,
            ];
        }

        $parts = explode('#', $comment, 2);

        $label = trim($parts[0]) !== '' ? trim($parts[0]) : $fallbackLabel;
        $inputType = isset($parts[1]) && trim($parts[1]) !== '' ? strtolower(trim($parts[1])) : $fallbackInputType;

        return [
            'label' => $label,
            'input_type' => $inputType,
        ];
    }
}