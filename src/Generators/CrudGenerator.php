<?php

namespace Ridtichai\ExistingDbMigrationGenerator\Generators;

use Illuminate\Support\Str;
use RuntimeException;

class CrudGenerator
{
    protected $fieldResolver;

    public function __construct(CrudFieldResolver $fieldResolver)
    {
        $this->fieldResolver = $fieldResolver;
    }

    public function generate(array $tableMeta, array $options = [])
    {
        $table = $tableMeta['name'];
        $force = !empty($options['force']);
        $fields = $this->fieldResolver->resolve($tableMeta);

        $modelClass = Str::studly(Str::singular($table));
        $modelVariable = Str::camel($modelClass);
        $pluralVariable = Str::camel(Str::plural($modelClass));
        $controllerClass = $modelClass . 'Controller';

        $crudConfig = (array) config('existing-db-migration-generator.crud', []);
        $layout = isset($crudConfig['layout']) ? $crudConfig['layout'] : 'layouts.index';
        $viewParentPath = isset($options['view_parent_path']) && $options['view_parent_path']
            ? trim($options['view_parent_path'], '/')
            : '';
        $formColumns = isset($crudConfig['form_columns']) ? (int) $crudConfig['form_columns'] : 2;
        $modelNamespace = isset($crudConfig['model_namespace']) ? $crudConfig['model_namespace'] : 'App\\Models';
        $controllerNamespace = isset($crudConfig['controller_namespace']) ? $crudConfig['controller_namespace'] : 'App\\Http\\Controllers';

        $viewDotBase = $this->buildViewDotBase($viewParentPath, $table);
        $viewDirectory = $this->buildViewDirectory($viewParentPath, $table);
        $controllerDirectory = $this->buildControllerDirectory($controllerNamespace);
        $controllerPath = $controllerDirectory . DIRECTORY_SEPARATOR . $controllerClass . '.php';

        $resourceTitle = Str::headline($table);
        $singleTitle = Str::headline(Str::singular($table));

        $relatedModels = $this->extractRelatedModels($fields);

        $controllerContent = $this->renderStub('crud/controller.stub', [
            '{{controllerNamespace}}' => $controllerNamespace,
            '{{modelNamespace}}' => $modelNamespace,
            '{{modelClass}}' => $modelClass,
            '{{controllerClass}}' => $controllerClass,
            '{{modelVariable}}' => $modelVariable,
            '{{pluralVariable}}' => $pluralVariable,
            '{{tableName}}' => $table,
            '{{indexView}}' => $viewDotBase . '.index',
            '{{createView}}' => $viewDotBase . '.create',
            '{{editView}}' => $viewDotBase . '.edit',
            '{{showView}}' => $viewDotBase . '.show',
            '{{resourceTitle}}' => $resourceTitle,
            '{{singleTitle}}' => $singleTitle,
            '{{relatedModelUses}}' => $this->buildRelatedModelUses($modelNamespace, $relatedModels),
            '{{hashUse}}' => $this->hasPasswordField($fields) ? "use Illuminate\\Support\\Facades\\Hash;\n" : '',
            '{{validationRules}}' => $this->buildValidationRules($fields),
            '{{createRelatedLoads}}' => $this->buildRelatedLoads($relatedModels, 2),
            '{{editRelatedLoads}}' => $this->buildRelatedLoads($relatedModels, 2),
            '{{createViewData}}' => $this->buildCreateViewData($viewDotBase . '.create', $relatedModels),
            '{{editViewData}}' => $this->buildEditViewData($viewDotBase . '.edit', $modelVariable, $relatedModels),
            '{{storeCreate}}' => $this->buildCreateArray($fields, $modelClass, 2),
            '{{updateAssignments}}' => $this->buildUpdateAssignments($fields, $modelVariable, 2),
        ]);

        $indexContent = $this->renderStub('crud/index.blade.stub', [
            '{{layout}}' => $layout,
            '{{title}}' => $resourceTitle,
            '{{sectionTitle}}' => $resourceTitle,
            '{{routeName}}' => $table,
            '{{pluralVariable}}' => $pluralVariable,
            '{{indexHeaders}}' => $this->buildIndexHeaders($fields),
            '{{indexCells}}' => $this->buildIndexCells($fields, '$value', $table),
            '{{emptyColspan}}' => (string) ($this->countVisibleIndexFields($fields) + 2),
            '{{emptyText}}' => 'ยังไม่มีข้อมูล',
            '{{pageTitle}}' => $resourceTitle,
        ]);

        $createContent = $this->renderStub('crud/create.blade.stub', [
            '{{layout}}' => $layout,
            '{{title}}' => 'เพิ่ม' . $singleTitle,
            '{{sectionTitle}}' => 'เพิ่ม' . $singleTitle,
            '{{routeName}}' => $table,
            '{{formInclude}}' => $viewDotBase . '._form',
            '{{submitLabel}}' => 'บันทึก',
            '{{cancelLabel}}' => 'ยกเลิก',
            '{{pageTitle}}' => 'เพิ่ม' . $singleTitle,
        ]);

        $editContent = $this->renderStub('crud/edit.blade.stub', [
            '{{layout}}' => $layout,
            '{{title}}' => 'แก้ไข' . $singleTitle,
            '{{sectionTitle}}' => 'แก้ไข' . $singleTitle,
            '{{routeName}}' => $table,
            '{{formInclude}}' => $viewDotBase . '._form',
            '{{modelVariable}}' => $modelVariable,
            '{{submitLabel}}' => 'บันทึก',
            '{{cancelLabel}}' => 'ยกเลิก',
            '{{pageTitle}}' => 'แก้ไข' . $singleTitle,
        ]);

        $showContent = $this->renderStub('crud/show.blade.stub', [
            '{{layout}}' => $layout,
            '{{sectionTitle}}' => 'รายละเอียด' . $singleTitle,
            '{{routeName}}' => $table,
            '{{modelVariable}}' => $modelVariable,
            '{{showRows}}' => $this->buildShowRows($fields, '$' . $modelVariable),
            '{{pageTitle}}' => 'รายละเอียด' . $singleTitle,
        ]);

        $formContent = $this->renderStub('crud/_form.blade.stub', [
            '{{formRows}}' => $this->buildFormRows($fields, $formColumns),
        ]);

        $files = [];

        $this->ensureDirectory($controllerDirectory);
        $this->ensureDirectory($viewDirectory);

        $this->writeFile($controllerPath, $controllerContent, $force);
        $files[] = $controllerPath;

        $indexPath = $viewDirectory . DIRECTORY_SEPARATOR . 'index.blade.php';
        $createPath = $viewDirectory . DIRECTORY_SEPARATOR . 'create.blade.php';
        $editPath = $viewDirectory . DIRECTORY_SEPARATOR . 'edit.blade.php';
        $showPath = $viewDirectory . DIRECTORY_SEPARATOR . 'show.blade.php';
        $formPath = $viewDirectory . DIRECTORY_SEPARATOR . '_form.blade.php';

        $this->writeFile($indexPath, $indexContent, $force);
        $this->writeFile($createPath, $createContent, $force);
        $this->writeFile($editPath, $editContent, $force);
        $this->writeFile($showPath, $showContent, $force);
        $this->writeFile($formPath, $formContent, $force);

        $files[] = $indexPath;
        $files[] = $createPath;
        $files[] = $editPath;
        $files[] = $showPath;
        $files[] = $formPath;

        return [
            'files' => $files,
            'controller_class' => $controllerClass,
        ];
    }

    protected function buildViewDotBase($viewParentPath, $table)
    {
        $prefix = trim(str_replace('/', '.', $viewParentPath), '.');

        return $prefix !== '' ? $prefix . '.' . $table : $table;
    }

    protected function buildViewDirectory($viewParentPath, $table)
    {
        $path = base_path('resources/views');

        if ($viewParentPath !== '') {
            $path .= DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $viewParentPath);
        }

        return $path . DIRECTORY_SEPARATOR . $table;
    }

    protected function buildControllerDirectory($controllerNamespace)
    {
        $relativeNamespace = preg_replace('/^App\\\\/', '', $controllerNamespace);
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeNamespace);

        return base_path($relativePath !== '' ? 'app' . DIRECTORY_SEPARATOR . $relativePath : 'app');
    }

    protected function extractRelatedModels(array $fields)
    {
        $relatedModels = [];

        foreach ($fields as $field) {
            if (!empty($field['is_foreign']) && !empty($field['related_model']) && !empty($field['related_variable'])) {
                $key = $field['related_model'];

                $relatedModels[$key] = [
                    'model' => $field['related_model'],
                    'variable' => $field['related_variable'],
                ];
            }
        }

        return array_values($relatedModels);
    }

    protected function buildRelatedModelUses($modelNamespace, array $relatedModels)
    {
        if (empty($relatedModels)) {
            return '';
        }

        $lines = [];

        foreach ($relatedModels as $related) {
            $lines[] = 'use ' . trim($modelNamespace, '\\') . '\\' . $related['model'] . ';';
        }

        return implode("\n", $lines) . "\n";
    }

    protected function hasPasswordField(array $fields)
    {
        foreach ($fields as $field) {
            if ($field['input_type'] === 'password') {
                return true;
            }
        }

        return false;
    }

    protected function buildRelatedLoads(array $relatedModels, $indentLevel)
    {
        if (empty($relatedModels)) {
            return '';
        }

        $indent = str_repeat('    ', $indentLevel);
        $lines = [];

        foreach ($relatedModels as $related) {
            $lines[] = $indent . '$' . $related['variable'] . ' = ' . $related['model'] . '::all();';
        }

        return implode("\n", $lines) . "\n";
    }

    protected function buildCreateViewData($createView, array $relatedModels)
    {
        if (empty($relatedModels)) {
            return "return view('{$createView}');";
        }

        $vars = [];

        foreach ($relatedModels as $related) {
            $vars[] = "'" . $related['variable'] . "'";
        }

        return "return view('{$createView}', compact(" . implode(', ', $vars) . "));";
    }

    protected function buildEditViewData($editView, $modelVariable, array $relatedModels)
    {
        $vars = ["'" . $modelVariable . "'"];

        foreach ($relatedModels as $related) {
            $vars[] = "'" . $related['variable'] . "'";
        }

        return "return view('{$editView}', compact(" . implode(', ', $vars) . "));";
    }

    protected function buildValidationRules(array $fields)
    {
        $lines = [];

        foreach ($fields as $field) {
            $rules = [];

            if ($field['required'] && $field['input_type'] !== 'password') {
                $rules[] = 'required';
            } else {
                $rules[] = 'nullable';
            }

            switch ($field['input_type']) {
                case 'email':
                    $rules[] = 'email';
                    $rules[] = 'max:255';
                    break;
                case 'password':
                    $rules[] = 'nullable';
                    $rules[] = 'string';
                    if (!empty($field['length'])) {
                        $rules[] = 'max:' . (int) $field['length'];
                    } else {
                        $rules[] = 'max:255';
                    }
                    break;
                case 'textarea':
                case 'text':
                    $rules[] = 'string';
                    if (!empty($field['length'])) {
                        $rules[] = 'max:' . (int) $field['length'];
                    }
                    break;
                case 'number':
                    $rules[] = in_array($field['column_type'], ['smallint', 'integer', 'int', 'bigint'], true) ? 'integer' : 'numeric';
                    break;
                case 'date':
                case 'datetime':
                case 'datetime-local':
                case 'time':
                    $rules[] = 'date';
                    break;
                case 'checkbox':
                    $rules[] = 'boolean';
                    break;
                case 'select':
                case 'radio':
                    $rules[] = 'nullable';
                    if (!empty($field['is_foreign'])) {
                        $rules[] = 'integer';
                    }
                    break;
                default:
                    $rules[] = 'string';
                    if (!empty($field['length'])) {
                        $rules[] = 'max:' . (int) $field['length'];
                    }
                    break;
            }

            $rules = array_values(array_unique($rules));
            $lines[] = "            '" . $field['name'] . "' => '" . implode('|', $rules) . "',";
        }

        return implode("\n", $lines);
    }

    protected function buildCreateArray(array $fields, $modelClass, $indentLevel)
    {
        $indent = str_repeat('    ', $indentLevel);
        $lines = [];

        $lines[] = $indent . '$item = ' . $modelClass . '::create([';

        foreach ($fields as $field) {
            $name = $field['name'];

            if ($field['input_type'] === 'password') {
                $lines[] = $indent . "    '{$name}' => isset(\$data['{$name}']) ? Hash::make(\$data['{$name}']) : null,";
            } else {
                $lines[] = $indent . "    '{$name}' => \$data['{$name}'] ?? null,";
            }
        }

        $lines[] = $indent . ']);';

        return implode("\n", $lines) . "\n";
    }

    protected function buildUpdateAssignments(array $fields, $modelVariable, $indentLevel)
    {
        $indent = str_repeat('    ', $indentLevel);
        $lines = [];

        foreach ($fields as $field) {
            $name = $field['name'];

            if ($field['input_type'] === 'password') {
                $lines[] = $indent . "if (!empty(\$data['{$name}'])) {";
                $lines[] = $indent . "    \${$modelVariable}->{$name} = Hash::make(\$data['{$name}']);";
                $lines[] = $indent . "}";
            } else {
                $lines[] = $indent . "\${$modelVariable}->{$name} = \$data['{$name}'] ?? null;";
            }
        }

        return implode("\n", $lines) . "\n";
    }

    protected function buildIndexHeaders(array $fields)
    {
        $lines = ["                    <th>#</th>"];

        foreach ($fields as $field) {
            if ($field['index_visible']) {
                $lines[] = '                    <th>' . $field['label'] . '</th>';
            }
        }

        $lines[] = '                    <th>การจัดการ</th>';

        return implode("\n", $lines);
    }

    protected function buildIndexCells(array $fields, $itemVariable, $routeName)
    {
        $lines = ["                        <td>{{ \$key + 1 }}</td>"];

        foreach ($fields as $field) {
            if (!$field['index_visible']) {
                continue;
            }

            if ($field['input_type'] === 'checkbox') {
                $lines[] = "                        <td>{{ {$itemVariable}->{$field['name']} ? 'Yes' : 'No' }}</td>";
                continue;
            }

            $lines[] = "                        <td>{{ {$itemVariable}->{$field['name']} }}</td>";
        }

        $lines[] = '                        <td>';
        $lines[] = '                            <a href="{{ route(\'' . $routeName . '.show\', ' . $itemVariable . '->id) }}" class="btn btn-sm btn-info">View</a>';
        $lines[] = '                            <a href="{{ route(\'' . $routeName . '.edit\', ' . $itemVariable . '->id) }}" class="btn btn-sm btn-warning">แก้ไข</a>';
        $lines[] = '                            <form action="{{ route(\'' . $routeName . '.destroy\', ' . $itemVariable . '->id) }}" method="POST" class="delete-form" style="display:inline-block;">';
        $lines[] = '                                @csrf';
        $lines[] = '                                @method(\'DELETE\')';
        $lines[] = '                                <button type="submit" class="btn btn-sm btn-danger">ลบ</button>';
        $lines[] = '                            </form>';
        $lines[] = '                        </td>';

        return implode("\n", $lines);
    }

    protected function countVisibleIndexFields(array $fields)
    {
        $count = 0;

        foreach ($fields as $field) {
            if ($field['index_visible']) {
                $count++;
            }
        }

        return $count;
    }

    protected function buildFormRows(array $fields, $formColumns)
    {
        $visibleFields = [];
        $hiddenFields = [];

        foreach ($fields as $field) {
            if ($field['input_type'] === 'hidden') {
                $hiddenFields[] = $field;
            } elseif ($field['form_visible']) {
                $visibleFields[] = $field;
            }
        }

        $colClass = $this->resolveBootstrapColumnClass($formColumns);
        $rows = [];

        foreach ($hiddenFields as $field) {
            $rows[] = $this->buildHiddenField($field);
        }

        $chunks = array_chunk($visibleFields, max(1, (int) $formColumns));

        foreach ($chunks as $chunk) {
            $rowLines = [];
            $rowLines[] = '<div class="row">';

            foreach ($chunk as $field) {
                $rowLines[] = $this->buildFieldBlock($field, $colClass);
            }

            $rowLines[] = '</div>';
            $rows[] = implode("\n", $rowLines);
        }

        return implode("\n\n", $rows);
    }

    protected function buildShowRows(array $fields, $itemVariable)
    {
        $lines = [];

        foreach ($fields as $field) {
            if (!$field['index_visible']) {
                continue;
            }

            $lines[] = '                <div class="row mb-3">';
            $lines[] = '                    <div class="col-md-3 fw-bold">' . $field['label'] . '</div>';
            $lines[] = '                    <div class="col-md-9">{{ ' . $itemVariable . '->' . $field['name'] . ' }}</div>';
            $lines[] = '                </div>';
        }

        return implode("\n", $lines);
    }

    protected function resolveBootstrapColumnClass($formColumns)
    {
        switch ((int) $formColumns) {
            case 1:
                return 'col-md-12';
            case 2:
                return 'col-md-6';
            case 3:
                return 'col-md-4';
            case 4:
                return 'col-md-3';
            default:
                return 'col-md-6';
        }
    }

    protected function buildHiddenField(array $field)
    {
        return '<input type="hidden" name="' . $field['name'] . '" value="{{ old(\'' . $field['name'] . '\', isset($item) ? $item->' . $field['name'] . ' : \'\') }}">';
    }

    protected function buildFieldBlock(array $field, $colClass)
    {
        $name = $field['name'];
        $label = $field['label'];
        $required = $field['required'] ? 'required' : '';
        $oldValue = "old('{$name}', isset(\$item) ? \$item->{$name} : '')";

        $lines = [];
        $lines[] = '    <div class="' . $colClass . ' mb-3">';
        $lines[] = '        <label for="' . $name . '" class="form-label">' . $label . '</label>';

        switch ($field['input_type']) {
            case 'textarea':
                $lines[] = '        <textarea class="form-control @error(\'' . $name . '\') is-invalid @enderror" id="' . $name . '" name="' . $name . '" ' . $required . '>{{ ' . $oldValue . ' }}</textarea>';
                break;
            case 'select':
                if (!empty($field['is_foreign']) && !empty($field['related_variable'])) {
                    $optionVar = '$' . $field['related_variable'];

                    $lines[] = '        <select class="form-select @error(\'' . $name . '\') is-invalid @enderror" id="' . $name . '" name="' . $name . '" ' . $required . '>';
                    $lines[] = '            <option value="">- เลือก -</option>';
                    $lines[] = '            @foreach ((' . $optionVar . ' ?? collect()) as $option)';
                    $lines[] = '                <option value="{{ $option->id }}" {{ (string) ' . $oldValue . ' === (string) $option->id ? \'selected\' : \'\' }}>';
                    $lines[] = '                    {{ data_get($option, \'name\') ?? data_get($option, \'title\') ?? data_get($option, \'label\') ?? $option->id }}';
                    $lines[] = '                </option>';
                    $lines[] = '            @endforeach';
                    $lines[] = '        </select>';
                } else {
                    $lines[] = '        <select class="form-select @error(\'' . $name . '\') is-invalid @enderror" id="' . $name . '" name="' . $name . '" ' . $required . '>';
                    $lines[] = '            <option value="">- เลือก -</option>';
                    $lines[] = '        </select>';
                }
                break;
            case 'checkbox':
                $lines[] = '        <div class="form-check mt-2">';
                $lines[] = '            <input type="hidden" name="' . $name . '" value="0">';
                $lines[] = '            <input class="form-check-input @error(\'' . $name . '\') is-invalid @enderror" type="checkbox" id="' . $name . '" name="' . $name . '" value="1" {{ ' . $oldValue . ' ? \'checked\' : \'\' }}>';
                $lines[] = '            <label class="form-check-label" for="' . $name . '">' . $label . '</label>';
                $lines[] = '        </div>';
                break;
            case 'password':
                $lines[] = '        <input type="password" class="form-control @error(\'' . $name . '\') is-invalid @enderror" id="' . $name . '" name="' . $name . '" autocomplete="new-password">';
                break;
            case 'date':
            case 'time':
            case 'email':
            case 'number':
            case 'datetime-local':
                $lines[] = '        <input type="' . $field['input_type'] . '" class="form-control @error(\'' . $name . '\') is-invalid @enderror" id="' . $name . '" name="' . $name . '" value="{{ ' . $oldValue . ' }}" ' . $required . '>';
                break;
            default:
                $lines[] = '        <input type="text" class="form-control @error(\'' . $name . '\') is-invalid @enderror" id="' . $name . '" name="' . $name . '" value="{{ ' . $oldValue . ' }}" ' . $required . '>';
                break;
        }

        $lines[] = '        @error(\'' . $name . '\')';
        $lines[] = '            <div class="invalid-feedback">{{ $message }}</div>';
        $lines[] = '        @enderror';
        $lines[] = '    </div>';

        return implode("\n", $lines);
    }

    protected function renderStub($stub, array $replacements)
    {
        $stubPath = __DIR__ . '/../../resources/stubs/' . $stub;

        if (!file_exists($stubPath)) {
            throw new RuntimeException('Stub not found: ' . $stubPath);
        }

        $content = file_get_contents($stubPath);

        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        return $content;
    }

    protected function ensureDirectory($directory)
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    protected function writeFile($path, $content, $force)
    {
        if (file_exists($path) && !$force) {
            throw new RuntimeException('File already exists: ' . $path . '. Use --force to overwrite.');
        }

        file_put_contents($path, $content);
    }
}