<?php

declare(strict_types=1);

namespace App\Tables;

use App\CustomFields\CustomFieldProvider;
use App\CustomFields\CustomFieldRelationLabelResolver;
use App\CustomFields\FieldTypeRegistry;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\User;
use App\Services\Table\FilterApplier;
use App\Tables\CustomFields\CustomFieldColumnBuilder;
use App\Tables\CustomFields\DelegatesUnaugmentedTableMethods;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Decorator that injects a custom-fieldable domain's active custom fields
 * into its TableDefinition (spec 0021, T6): columns/filters/sort/search/
 * export/distinct-values on `custom.<key>` come from this class alone — the
 * wrapped $inner definition never knows custom fields exist (zero
 * per-module code, AC-014..017).
 *
 * Pure passthrough when the entity has no ACTIVE custom field definitions
 * (the common case for most domains today, and every domain before its first
 * field is ever defined), so wrapping every table via TableRegistry::resolve()
 * is free until a field actually exists for it.
 *
 * `custom_field_values` is joined AT MOST ONCE regardless of how many custom
 * fields are filtered/sorted in a single request (AC-015): the join lives in
 * baseQuery(), not per-column. It is a SUBQUERY join (not the raw table)
 * aliased back to the real table name so every FieldTypeHandler's
 * hardcoded `custom_field_values.values-><key>` column expression keeps
 * working unchanged, while exposing ONLY `entity_id`/`values` — the raw
 * table's OWN `id`/`created_at`/`updated_at` would otherwise collide with
 * the host table's same-named columns and make every unqualified ORDER
 * BY/WHERE on those columns ambiguous SQL once joined.
 *
 * @see TableRegistry::resolve()
 */
class CustomFieldAwareTableDefinition implements TableDefinition
{
    use DelegatesUnaugmentedTableMethods;

    private const string VALUES_JOIN_ALIAS = 'custom_field_values';

    private const string VALUES_JSON_ATTRIBUTE = 'custom_field_values_json';

    /** @var Collection<int, CustomFieldDefinition>|null */
    private ?Collection $definitions = null;

    /** @var array<string, CustomFieldDefinition>|null */
    private ?array $definitionsByColumnId = null;

    public function __construct(
        private readonly TableDefinition $inner,
        private readonly string $entityType,
        private readonly CustomFieldProvider $provider,
        private readonly FieldTypeRegistry $typeRegistry,
        private readonly CustomFieldColumnBuilder $columnBuilder,
        private readonly CustomFieldRelationLabelResolver $relationLabels,
        private readonly FilterApplier $filterApplier,
    ) {}

    /**
     * @return Builder<Model>
     */
    public function baseQuery(): Builder
    {
        $query = $this->inner->baseQuery();

        if ($this->definitions()->isEmpty()) {
            return $query;
        }

        $table = $this->mainTable();

        $valuesSubquery = CustomFieldValue::query()
            ->where('entity_type', $this->entityType)
            ->select(['entity_id', 'values']);

        $query->leftJoinSub($valuesSubquery, self::VALUES_JOIN_ALIAS, "{$table}.id", '=', self::VALUES_JOIN_ALIAS.'.entity_id')
            ->addSelect("{$table}.*")
            ->addSelect(self::VALUES_JOIN_ALIAS.'.values as '.self::VALUES_JSON_ATTRIBUTE);

        return $query;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        $definitions = $this->definitions();

        if ($definitions->isEmpty()) {
            return $this->inner->columns();
        }

        return [
            ...$this->inner->columns(),
            ...$definitions->map(fn (CustomFieldDefinition $definition): array => $this->columnBuilder->raw($definition))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        $mapped = $this->inner->mapRow($actor, $row);
        $definitions = $this->definitions();

        if ($definitions->isEmpty()) {
            return $mapped;
        }

        $stored = $this->storedValues($row);

        foreach ($definitions as $definition) {
            $mapped[$this->columnBuilder->id($definition)] = $this->cellValue($definition, $stored[$definition->key] ?? null);
        }

        return $mapped;
    }

    public function sortableColumnIds(): array
    {
        return [...$this->inner->sortableColumnIds(), ...$this->customColumnIds()];
    }

    public function filterableColumnIds(): array
    {
        return array_keys($this->filterableColumnMap());
    }

    public function searchableColumnIds(): array
    {
        return [...$this->inner->searchableColumnIds(), ...$this->searchableCustomColumnIds()];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveConfig(User $actor): array
    {
        $config = $this->inner->resolveConfig($actor);
        $definitions = $this->definitions();

        if ($definitions->isEmpty()) {
            return $config;
        }

        $order = count($config['columns']);
        $customColumns = [];

        foreach ($definitions as $definition) {
            $order++;
            $customColumns[] = $this->columnBuilder->resolved($definition, $order);
        }

        $config['columns'] = [...$config['columns'], ...$customColumns];
        $config['searchable'] = [...$config['searchable'], ...$this->searchableCustomColumnIds()];

        return $config;
    }

    /**
     * Column-layout allow-list (visible/width/order) augmented with the custom
     * columns. WITHOUT this override the trait delegates to $inner, whose
     * layout is built from native columns only — so a user's column-visibility
     * preference for a `custom.<key>` column was rejected by the allow-list
     * (TablePreferencesRequest's `Rule::in`) and the whole save 422'd, making
     * the column reappear hidden on reload (spec 0021 / spec 0001).
     *
     * @return array<string, array<string, mixed>>
     */
    public function defaultColumnLayout(): array
    {
        $layout = $this->inner->defaultColumnLayout();
        $definitions = $this->definitions();

        if ($definitions->isEmpty()) {
            return $layout;
        }

        $order = count($layout);

        foreach ($definitions as $definition) {
            $order++;
            $column = $this->columnBuilder->resolved($definition, $order);
            $layout[$column['id']] = [
                'visible' => (bool) ($column['visible'] ?? false),
                'width' => $column['width'] ?? null,
                'order' => (int) ($column['order'] ?? $order),
            ];
        }

        return $layout;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function filterableColumnMap(): array
    {
        $map = $this->inner->filterableColumnMap();

        foreach ($this->definitions() as $definition) {
            $map[$this->columnBuilder->id($definition)] = $this->columnBuilder->raw($definition);
        }

        return $map;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        $definition = $this->definitionFor($columnId);

        if ($definition === null) {
            return $this->inner->applyDerivedFilter($query, $columnId, $columnConfig, $filter);
        }

        // AG Grid's agMultiColumnFilter (text/number custom columns) and its
        // combined typed conditions arrive wrapped as `multi` (filterModels[])
        // or `{operator, conditions[]}`. The per-type handlers only read the
        // FLAT shape, so those envelopes were silently dropped (no WHERE, every
        // row returned). Delegate them to the native FilterApplier — the SAME
        // unwrapping used for native derived columns — pointed at the JSON-path
        // column. Flat set/boolean stay on the handler, which additionally
        // supports multi-valued enum/relation JSON containment (whereJsonContains).
        if (($filter['filterType'] ?? null) === 'multi' || isset($filter['conditions'])) {
            $this->filterApplier->apply($query, $this->valuesJsonColumn($definition->key), $columnConfig, $filter);

            return true;
        }

        $this->columnBuilder->handlerFor($definition)->applyFilter($query, $this->valuesJsonBaseColumn(), $definition->key, $filter);

        return true;
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        $definition = $this->definitionFor($columnId);

        if ($definition === null) {
            return $this->inner->applyDerivedSort($query, $columnId, $direction);
        }

        $this->columnBuilder->handlerFor($definition)->applySort($query, $this->valuesJsonBaseColumn(), $definition->key, $direction);

        return true;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        $definition = $this->definitionFor($columnId);

        if ($definition === null) {
            return $this->inner->distinctValues($actor, $columnId, $columnConfig, $search, $query, $limit);
        }

        $values = $this->columnBuilder->handlerFor($definition)->distinctValues($query, $this->valuesJsonBaseColumn(), $definition->key);

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);
            $values = array_values(array_filter(
                $values,
                static fn (mixed $value): bool => str_contains(mb_strtolower((string) $value), $needle),
            ));
        }

        return array_slice($values, 0, $limit);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        $definition = $this->definitionFor($columnId);

        if ($definition === null) {
            return $this->inner->applyDerivedSearch($query, $columnId, $pattern);
        }

        $query->orWhere($this->valuesJsonColumn($definition->key), 'like', $pattern);

        return true;
    }

    /**
     * The bound JSON-path column expression for a custom field's stored value,
     * against the `custom_field_values` subquery join (baseQuery()). `$key` is
     * always an allow-listed definition key resolved from the request, never
     * raw input (backend.md §8).
     */
    private function valuesJsonColumn(string $key): string
    {
        return self::VALUES_JOIN_ALIAS.'.values->'.$key;
    }

    /**
     * The base JSON column (spec 0064, T-M1) FieldTypeHandler's grid-side
     * methods (applyFilter/applySort/distinctValues) resolve `<key>` against
     * — the `custom_field_values` subquery join's own `values` column, never
     * the raw table (see baseQuery()'s docblock).
     */
    private function valuesJsonBaseColumn(): string
    {
        return self::VALUES_JOIN_ALIAS.'.values';
    }

    /**
     * Active definitions for this entity_type (empty ⇒ pure passthrough
     * everywhere above), memoized for the lifetime of this instance.
     *
     * @return Collection<int, CustomFieldDefinition>
     */
    private function definitions(): Collection
    {
        return $this->definitions ??= $this->provider->definitionsFor($this->entityType);
    }

    /**
     * @return array<int, string>
     */
    private function customColumnIds(): array
    {
        return $this->definitions()->map(fn (CustomFieldDefinition $definition): string => $this->columnBuilder->id($definition))->all();
    }

    /**
     * @return array<int, string>
     */
    private function searchableCustomColumnIds(): array
    {
        return $this->definitions()
            ->filter(fn (CustomFieldDefinition $definition): bool => $this->columnBuilder->handlerFor($definition)->filterType() === 'text')
            ->map(fn (CustomFieldDefinition $definition): string => $this->columnBuilder->id($definition))
            ->all();
    }

    /**
     * The active definition owning `$columnId`, or null when `$columnId` is
     * not a `custom.<key>` column (native columns fall through to $inner).
     */
    private function definitionFor(string $columnId): ?CustomFieldDefinition
    {
        if ($this->definitionsByColumnId === null) {
            $this->definitionsByColumnId = [];

            foreach ($this->definitions() as $definition) {
                $this->definitionsByColumnId[$this->columnBuilder->id($definition)] = $definition;
            }
        }

        return $this->definitionsByColumnId[$columnId] ?? null;
    }

    /**
     * The host table name (e.g. "companies"), read from the wrapped
     * definition's own model class — never hardcoded per domain.
     */
    private function mainTable(): string
    {
        $modelClass = $this->inner->modelClass();

        return (new $modelClass)->getTable();
    }

    /**
     * Decode the row's joined `custom_field_values.values` JSON (aliased as
     * `custom_field_values_json` by baseQuery()) into its raw {key: value}
     * map. Absent for a row with no `custom_field_values` row yet (never
     * saved a custom field) — treated as "no values".
     *
     * @return array<string, mixed>
     */
    private function storedValues(Model $row): array
    {
        $raw = $row->getAttribute(self::VALUES_JSON_ATTRIBUTE);

        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Resolve one stored value into its row-payload shape: the handler's own
     * read-side resolution, further hydrated to a display label for
     * `relation` fields (mapRow only — the detail/read API keeps raw ids).
     */
    private function cellValue(CustomFieldDefinition $definition, mixed $stored): mixed
    {
        $handler = $this->columnBuilder->handlerFor($definition);
        $resolved = $handler->resolveForRead($stored, $definition);

        if ($definition->type !== 'relation') {
            return $resolved;
        }

        return $this->relationLabels->resolve($definition, $resolved);
    }
}
