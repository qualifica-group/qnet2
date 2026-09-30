<?php

declare(strict_types=1);

namespace App\CustomFields\Table;

use App\CustomFields\FieldTypeRegistry;
use App\CustomFields\Types\FieldTypeHandler;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldOption;
use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;

/**
 * Definition-agnostic core of the `table` field type (spec 0180), built from
 * the sole `config` + required flag so custom fields and attributes share it.
 * Cells are validated/normalized by the existing scalar handlers, through a
 * transient (never persisted) definition per column.
 */
final class TableFieldSchema
{
    public const array COLUMN_TYPES = [
        'text', 'textarea', 'integer', 'decimal', 'boolean', 'enum',
        'date', 'datetime', 'time', 'email', 'url', 'color',
    ];

    public const array SUMMARY_COLUMN_TYPES = ['date', 'datetime', 'time', 'integer', 'decimal', 'text', 'enum'];

    public const array SUMMARY_STRATEGIES = ['selected', 'max', 'min'];

    public const array RESERVED_KEYS = ['id'];

    private const array NUMERIC_TYPES = ['integer', 'decimal'];

    /** @var array<string, array{definition: CustomFieldDefinition, handler: FieldTypeHandler}> */
    private array $columns = [];

    private function __construct(
        private readonly array $config,
        private readonly bool $required,
    ) {
        $registry = app(FieldTypeRegistry::class);

        foreach ($config['columns'] ?? [] as $column) {
            if (! is_array($column) || ! isset($column['key'], $column['type']) || ! $registry->has($column['type'])) {
                continue;
            }

            $this->columns[$column['key']] = [
                'definition' => $this->transientDefinition($column),
                'handler' => $registry->resolve($column['type']),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config, bool $required): self
    {
        return new self($config, $required);
    }

    /**
     * Nested rules on keys relative to the field value.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'rows' => $this->rowsRules(),
            'rows.*' => ['array'],
            'rows.*.id' => ['nullable', 'uuid', 'distinct'],
        ];

        foreach ($this->columns as $key => $column) {
            $rules["rows.*.{$key}"] = $column['handler']->validationRules($column['definition']);
        }

        if (($selectable = $this->selectableKey()) !== null) {
            $rules["rows.*.{$selectable}"] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, summary: string|int|float|bool|null}|null
     */
    public function normalize(mixed $input): ?array
    {
        $rows = is_array($input) && is_array($input['rows'] ?? null) ? $input['rows'] : null;

        if ($rows === null) {
            return null;
        }

        $normalized = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = $row['id'] ?? null;
            $normalized[] = [
                'id' => is_string($id) && Str::isUuid($id) ? $id : (string) Str::uuid(),
                ...$this->mapCells($row, fn (array $column, mixed $value): mixed => $column['handler']->normalizeForStore($value === '' ? null : $value, $column['definition'])),
            ];
        }

        return ['rows' => $normalized, 'summary' => $this->summarize($normalized)];
    }

    /**
     * Read-side view restricted to the currently defined columns (spec 0180 D-5).
     *
     * @return array{rows: array<int, array<string, mixed>>, summary: string|int|float|bool|null}|null
     */
    public function resolve(mixed $stored): ?array
    {
        if (! is_array($stored) || ! is_array($stored['rows'] ?? null)) {
            return null;
        }

        $rows = [];

        foreach ($stored['rows'] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[] = [
                'id' => $row['id'] ?? null,
                ...$this->mapCells($row, fn (array $column, mixed $value): mixed => $column['handler']->resolveForRead($value, $column['definition'])),
            ];
        }

        return ['rows' => $rows, 'summary' => $this->summarize($rows)];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  Closure(array{definition: CustomFieldDefinition, handler: FieldTypeHandler}, mixed): mixed  $cell
     * @return array<string, mixed>
     */
    private function mapCells(array $row, Closure $cell): array
    {
        $cells = [];

        foreach ($this->columns as $key => $column) {
            $cells[$key] = $cell($column, $row[$key] ?? null);
        }

        if (($selectable = $this->selectableKey()) !== null) {
            $cells[$selectable] = (bool) ($row[$selectable] ?? false);
        }

        return $cells;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function summarize(array $rows): string|int|float|bool|null
    {
        $summary = $this->config['summary'] ?? null;
        $column = is_array($summary) ? ($summary['column'] ?? null) : null;

        if (! is_string($column) || ! isset($this->columns[$column])) {
            return null;
        }

        if (($summary['strategy'] ?? null) === 'selected') {
            $selectable = $this->selectableKey();
            $selected = $selectable === null ? null : collect($rows)->first(fn (array $row): bool => $row[$selectable] === true);

            return $selected[$column] ?? null;
        }

        $values = collect($rows)->pluck($column)->filter(fn (mixed $value): bool => $value !== null)->values()->all();

        if ($values === []) {
            return null;
        }

        $compare = in_array($this->columns[$column]['definition']->type, self::NUMERIC_TYPES, true)
            ? fn (mixed $a, mixed $b): int => $a <=> $b
            : fn (mixed $a, mixed $b): int => strcmp((string) $a, (string) $b);

        usort($values, $compare);

        return ($summary['strategy'] ?? null) === 'max' ? end($values) : $values[0];
    }

    /**
     * @return array<int, mixed>
     */
    private function rowsRules(): array
    {
        $minRows = (int) ($this->config['min_rows'] ?? 0);
        $min = $this->required ? max(1, $minRows) : $minRows;
        $max = (int) ($this->config['max_rows'] ?? config('custom-fields.table.max_rows'));

        $rules = ['array'];

        if ($min > 0) {
            $rules[] = "min:{$min}";
        }

        $rules[] = "max:{$max}";

        if (($selectable = $this->selectableKey()) !== null) {
            $rules[] = $this->singleSelectionRule($selectable);
        }

        return $rules;
    }

    private function singleSelectionRule(string $selectable): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($selectable): void {
            $selected = collect((array) $value)
                ->filter(fn (mixed $row): bool => is_array($row) && filter_var($row[$selectable] ?? false, FILTER_VALIDATE_BOOLEAN))
                ->count();

            if ($selected > 1) {
                $fail("The {$attribute} may have at most one selected row.");
            }
        };
    }

    private function selectableKey(): ?string
    {
        $key = $this->config['selectable']['key'] ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * @param  array<string, mixed>  $column
     */
    private function transientDefinition(array $column): CustomFieldDefinition
    {
        $definition = new CustomFieldDefinition([
            'key' => $column['key'],
            'type' => $column['type'],
            'config' => $column['config'] ?? [],
            'validation' => ['required' => (bool) ($column['required'] ?? false)],
        ]);

        $options = collect($column['options'] ?? [])
            ->filter(fn (mixed $option): bool => is_array($option) && isset($option['value']))
            ->values()
            ->map(fn (array $option, int $index): CustomFieldOption => new CustomFieldOption([
                'value' => (string) $option['value'],
                'label' => $option['label'] ?? (string) $option['value'],
                'color' => $option['color'] ?? null,
                'sort_order' => $index,
            ]));

        return $definition->setRelation('options', new EloquentCollection($options->all()));
    }
}
