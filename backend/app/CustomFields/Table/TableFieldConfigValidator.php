<?php

declare(strict_types=1);

namespace App\CustomFields\Table;

/**
 * Validates the `config` of a `type=table` definition against the
 * TableFieldConfig contract (spec 0180), shared by custom fields and
 * attributes. Returns errors keyed by the contract's `config.*` paths.
 */
final class TableFieldConfigValidator
{
    private const string KEY_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';

    private const int MAX_LENGTH = 191;

    private const int MAX_COLOR_LENGTH = 32;

    private const array ENUM_DISPLAYS = ['select', 'radio'];

    /** @var array<string, string> */
    private array $errors = [];

    /**
     * @return array<string, string> error message keyed by validation path
     */
    public function validate(mixed $config): array
    {
        $this->errors = [];

        if (! is_array($config)) {
            $this->errors['config'] = 'A table field requires a config.';

            return $this->errors;
        }

        $columnKeys = $this->validateColumns($config['columns'] ?? null);
        $selectableKey = $this->validateSelectable($config['selectable'] ?? null, $columnKeys);
        $this->validateSummary($config['summary'] ?? null, $config['columns'] ?? [], $selectableKey);
        $this->validateRows($config['min_rows'] ?? null, $config['max_rows'] ?? null);

        return $this->errors;
    }

    /**
     * @return array<int, string> the valid column keys, for cross-checks
     */
    private function validateColumns(mixed $columns): array
    {
        $max = (int) config('custom-fields.table.max_columns');

        if (! is_array($columns) || ! array_is_list($columns) || $columns === []) {
            $this->errors['config.columns'] = 'A table requires at least one column.';

            return [];
        }

        if (count($columns) > $max) {
            $this->errors['config.columns'] = "A table may have at most {$max} columns.";
        }

        $keys = [];

        foreach ($columns as $index => $column) {
            $key = is_array($column) ? $this->validateColumn($index, $column, $keys) : null;

            if ($key === null && ! is_array($column)) {
                $this->errors["config.columns.{$index}"] = 'A column must be an object.';
            }

            if ($key !== null) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $column
     * @param  array<int, string>  $seenKeys
     */
    private function validateColumn(int $index, array $column, array $seenKeys): ?string
    {
        $prefix = "config.columns.{$index}";
        $key = $column['key'] ?? null;
        $validKey = null;

        if (! is_string($key) || preg_match(self::KEY_PATTERN, $key) !== 1) {
            $this->errors["{$prefix}.key"] = 'The column key must be lowercase snake_case starting with a letter.';
        } elseif (in_array($key, TableFieldSchema::RESERVED_KEYS, true)) {
            $this->errors["{$prefix}.key"] = 'The column key is reserved.';
        } elseif (in_array($key, $seenKeys, true)) {
            $this->errors["{$prefix}.key"] = 'Column keys must be unique.';
        } else {
            $validKey = $key;
        }

        $label = $column['label'] ?? null;

        if (! is_string($label) || $label === '' || mb_strlen($label) > self::MAX_LENGTH) {
            $this->errors["{$prefix}.label"] = 'The column label is required (max 191 characters).';
        }

        if (isset($column['required']) && ! is_bool($column['required'])) {
            $this->errors["{$prefix}.required"] = 'The column required flag must be a boolean.';
        }

        if (isset($column['config']) && ! is_array($column['config'])) {
            $this->errors["{$prefix}.config"] = 'The column config must be an object.';
        }

        $type = $column['type'] ?? null;

        if (! is_string($type) || ! in_array($type, TableFieldSchema::COLUMN_TYPES, true)) {
            $this->errors["{$prefix}.type"] = 'The column type is not supported.';
        } elseif ($type === 'enum') {
            $this->validateEnumColumn($prefix, $column);
        }

        return $validKey;
    }

    /**
     * @param  array<string, mixed>  $column
     */
    private function validateEnumColumn(string $prefix, array $column): void
    {
        $display = $column['config']['display'] ?? null;

        if ($display !== null && ! in_array($display, self::ENUM_DISPLAYS, true)) {
            $this->errors["{$prefix}.config.display"] = 'An enum column supports only select or radio display.';
        }

        $options = $column['options'] ?? null;

        if (! is_array($options) || $options === []) {
            $this->errors["{$prefix}.options"] = 'An enum column requires at least one option.';

            return;
        }

        $values = [];

        foreach ($options as $optionIndex => $option) {
            $value = is_array($option) ? ($option['value'] ?? null) : null;
            $label = is_array($option) ? ($option['label'] ?? null) : null;
            $color = is_array($option) ? ($option['color'] ?? null) : null;

            if (! is_string($value) || $value === '' || mb_strlen($value) > self::MAX_LENGTH) {
                $this->errors["{$prefix}.options.{$optionIndex}.value"] = 'The option value is required (max 191 characters).';
            } else {
                $values[] = $value;
            }

            if (! is_string($label) || $label === '' || mb_strlen($label) > self::MAX_LENGTH) {
                $this->errors["{$prefix}.options.{$optionIndex}.label"] = 'The option label is required (max 191 characters).';
            }

            if ($color !== null && (! is_string($color) || mb_strlen($color) > self::MAX_COLOR_LENGTH)) {
                $this->errors["{$prefix}.options.{$optionIndex}.color"] = 'The option color must be a string (max 32 characters).';
            }
        }

        if (count($values) !== count(array_unique($values))) {
            $this->errors["{$prefix}.options"] = 'Option values must be unique.';
        }
    }

    /**
     * @param  array<int, string>  $columnKeys
     * @return string|null the selectable key when valid
     */
    private function validateSelectable(mixed $selectable, array $columnKeys): ?string
    {
        if ($selectable === null) {
            return null;
        }

        if (! is_array($selectable)) {
            $this->errors['config.selectable'] = 'The selectable must be an object or null.';

            return null;
        }

        $key = $selectable['key'] ?? null;
        $label = $selectable['label'] ?? null;
        $validKey = null;

        if (! is_string($key) || preg_match(self::KEY_PATTERN, $key) !== 1) {
            $this->errors['config.selectable.key'] = 'The selectable key must be lowercase snake_case starting with a letter.';
        } elseif (in_array($key, TableFieldSchema::RESERVED_KEYS, true) || in_array($key, $columnKeys, true)) {
            $this->errors['config.selectable.key'] = 'The selectable key collides with a column or a reserved key.';
        } else {
            $validKey = $key;
        }

        if (! is_string($label) || $label === '' || mb_strlen($label) > self::MAX_LENGTH) {
            $this->errors['config.selectable.label'] = 'The selectable label is required (max 191 characters).';
        }

        return $validKey;
    }

    private function validateSummary(mixed $summary, mixed $columns, ?string $selectableKey): void
    {
        if ($summary === null) {
            return;
        }

        if (! is_array($summary)) {
            $this->errors['config.summary'] = 'The summary must be an object or null.';

            return;
        }

        $types = [];

        foreach (is_array($columns) ? $columns : [] as $column) {
            if (is_array($column) && is_string($column['key'] ?? null)) {
                $types[$column['key']] ??= $column['type'] ?? null;
            }
        }

        $column = $summary['column'] ?? null;

        if (! is_string($column) || ! array_key_exists($column, $types)) {
            $this->errors['config.summary.column'] = 'The summary column must be an existing column.';
        } elseif (! in_array($types[$column], TableFieldSchema::SUMMARY_COLUMN_TYPES, true)) {
            $this->errors['config.summary.column'] = 'The summary column type is not summarizable.';
        }

        $strategy = $summary['strategy'] ?? null;

        if (! is_string($strategy) || ! in_array($strategy, TableFieldSchema::SUMMARY_STRATEGIES, true)) {
            $this->errors['config.summary.strategy'] = 'The summary strategy is not supported.';
        } elseif ($strategy === 'selected' && $selectableKey === null) {
            $this->errors['config.summary.strategy'] = 'The selected strategy requires a selectable.';
        }
    }

    private function validateRows(mixed $minRows, mixed $maxRows): void
    {
        $ceiling = (int) config('custom-fields.table.max_rows');
        $minValid = $minRows === null;
        $maxValid = $maxRows === null;

        if ($minRows !== null) {
            $minValid = is_int($minRows) && $minRows >= 0;

            if (! $minValid) {
                $this->errors['config.min_rows'] = 'The minimum rows must be an integer >= 0.';
            }
        }

        if ($maxRows !== null) {
            $maxValid = is_int($maxRows) && $maxRows >= 1 && $maxRows <= $ceiling;

            if (! $maxValid) {
                $this->errors['config.max_rows'] = "The maximum rows must be an integer between 1 and {$ceiling}.";
            }
        }

        if ($minValid && $maxValid && $minRows !== null && $maxRows !== null && $minRows > $maxRows) {
            $this->errors['config.min_rows'] = 'The minimum rows may not exceed the maximum rows.';
        }
    }
}
