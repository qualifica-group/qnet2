<?php

declare(strict_types=1);

namespace App\Tables\CategoryTabs\Concerns;

use App\Models\User;
use App\Tables\RequestManagement\AttributeGridColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The READ side of the `attr.<code>` flexible columns of a grid whose columns
 * follow its advanced filters (spec 0374): the columns of the categories the
 * filters select, appended after the native ones, with their row values and
 * filter/sort/distinct hooks. Mirrors what
 * `RequestManagementScopedTableDefinition` does for Gestione Richieste, on top
 * of the shared `AttributeGridColumns`.
 *
 * The using class must expose `private readonly TableDefinition $inner` and
 * `private readonly AttributeGridColumns $attributeColumns` (already bound to
 * its `AttributeGridTarget`) and `private readonly AttributeColumnCategories
 * $columnCategories`. Where the values live, how they are filtered and
 * whether they are editable are protected hooks with the own-column default;
 * the contracts grid overrides them to read its source offer's JSON.
 *
 * Every `code` reaching a query comes from the server-resolved set
 * (allow-list), never from request input (backend.md §8).
 */
trait AppendsCategoryAttributeColumns
{
    /** @var array<int, int> the categories whose sets are shown (spec 0374, D-1); empty = no `attr.*` column */
    private array $attributeCategories = [];

    /** @var Collection<int, array<string, mixed>>|null */
    private ?Collection $categoryAttributesMemo = null;

    /** Widens the SSRM allow-lists to the union of every category (D-4, spec 0064). */
    private bool $allowListUnion = false;

    /** `POST /values` carries no filters: columns are looked up in the union (D-8). */
    private bool $lookupInUnion = false;

    /**
     * Set by the write trait after a successful write so the PATCH response
     * remap shows the written cell: the PATCH carries no category scope.
     *
     * @var Collection<int, array<string, mixed>>|null
     */
    private ?Collection $rowAttributesOverride = null;

    public function scopeToAllProductCategories(): void
    {
        $this->allowListUnion = true;
    }

    public function scopeAttributeColumnsForRows(array $advancedFilters): void
    {
        $this->useAttributeCategories($this->columnCategories->fromFilters($advancedFilters), union: true);
    }

    public function scopeAttributeColumnsForConfig(array $advancedFilters): void
    {
        $this->useAttributeCategories($this->columnCategories->fromFilters($advancedFilters), union: false);
    }

    public function scopeAttributeColumnsForValues(): void
    {
        $this->useAttributeCategories([], union: true);
        $this->lookupInUnion = true;
    }

    /**
     * UNSCOPED by design: the union's raw declarations, which
     * `TableCellUpdateService` looks a submitted `attr.<code>` up in.
     *
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        $attributes = $this->attributeColumns->union();

        if ($attributes->isEmpty()) {
            return $this->inner->columns();
        }

        return [...$this->inner->columns(), ...$this->rawAttributeColumns($attributes)];
    }

    public function mapRow(User $actor, Model $row): array
    {
        $mapped = $this->inner->mapRow($actor, $row);
        $attributes = $this->rowAttributesOverride ?? $this->categoryAttributes();

        if ($attributes->isEmpty()) {
            return $mapped;
        }

        return [...$mapped, ...$this->attributeColumns->mapValues($this->storedAttributeValues($row), $attributes)];
    }

    public function sortableColumnIds(): array
    {
        return [...$this->inner->sortableColumnIds(), ...$this->attributeColumns->columnIds($this->allowListedAttributes())];
    }

    public function filterableColumnIds(): array
    {
        return array_keys($this->filterableColumnMap());
    }

    public function searchableColumnIds(): array
    {
        return $this->inner->searchableColumnIds();
    }

    public function resolveConfig(User $actor): array
    {
        $config = $this->inner->resolveConfig($actor);
        $attributes = $this->categoryAttributes();
        $config['attributeCategoryIds'] = $attributes->isEmpty() ? [] : $this->attributeCategories;

        if ($attributes->isEmpty()) {
            return $config;
        }

        $config['columns'] = [
            ...$config['columns'],
            ...$this->attributeColumns->resolvedColumns($attributes, count($config['columns']), $this->attributeValuesEditable($actor)),
        ];

        return $config;
    }

    public function defaultColumnLayout(): array
    {
        $layout = $this->inner->defaultColumnLayout();
        $attributes = $this->attributeColumns->union();
        $order = count($layout);

        // Same baseline a tab shows (visible, no width): a hidden attribute
        // column must differ from its default, or the preferences delta drops
        // it and the column reappears on reload.
        foreach ($this->attributeColumns->rawColumns($attributes) as $column) {
            $order++;
            $layout[$column['id']] = ['visible' => true, 'width' => null, 'order' => $order];
        }

        return $layout;
    }

    public function filterableColumnMap(): array
    {
        $map = $this->inner->filterableColumnMap();

        foreach ($this->attributeColumns->rawColumns($this->allowListedAttributes()) as $column) {
            $map[$column['id']] = $column;
        }

        return $map;
    }

    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        $attributeRow = $this->attributeColumns->attributeRowFor($columnId, $this->lookupAttributes());

        if ($attributeRow === null) {
            // An `attr.*` of the union outside the current category is ignored (D-4).
            return $this->isAttributeColumn($columnId) || $this->inner->applyDerivedFilter($query, $columnId, $columnConfig, $filter);
        }

        $this->applyAttributeFilter($query, $attributeRow, $filter);

        return true;
    }

    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        $attributeRow = $this->attributeColumns->attributeRowFor($columnId, $this->lookupAttributes());

        if ($attributeRow === null) {
            return $this->isAttributeColumn($columnId) || $this->inner->applyDerivedSort($query, $columnId, $direction);
        }

        $this->applyAttributeSort($query, $attributeRow, $direction);

        return true;
    }

    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        $attributeRow = $this->attributeColumns->attributeRowFor($columnId, $this->lookupAttributes());

        if ($attributeRow === null) {
            return $this->inner->distinctValues($actor, $columnId, $columnConfig, $search, $query, $limit);
        }

        return $this->attributeDistinctValues($query, $attributeRow, $search, $limit);
    }

    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        return $this->inner->applyDerivedSearch($query, $columnId, $pattern);
    }

    /**
     * Hook: the gate of the cell edit; the contracts grid is read-only.
     */
    protected function attributeValuesEditable(User $actor): bool
    {
        return $this->attributeColumns->valuesEditable($actor, $this->inner->domain());
    }

    /**
     * Hook: the raw (PATCH allow-list) declarations of the union.
     *
     * @param  Collection<int, array<string, mixed>>  $attributes
     * @return array<int, array<string, mixed>>
     */
    protected function rawAttributeColumns(Collection $attributes): array
    {
        return $this->attributeColumns->rawColumns($attributes);
    }

    /**
     * Hook: the stored `code => value` map a row shows.
     *
     * @return array<string, mixed>|null
     */
    protected function storedAttributeValues(Model $row): ?array
    {
        return $row->attribute_values;
    }

    /** @param  array<string, mixed>  $attributeRow */
    protected function applyAttributeFilter(Builder $query, array $attributeRow, array $filter): void
    {
        $this->attributeColumns->applyFilter($query, $attributeRow, $filter);
    }

    /** @param  array<string, mixed>  $attributeRow */
    protected function applyAttributeSort(Builder $query, array $attributeRow, string $direction): void
    {
        $this->attributeColumns->applySort($query, $attributeRow, $direction);
    }

    /**
     * @param  array<string, mixed>  $attributeRow
     * @return array<int, string>
     */
    protected function attributeDistinctValues(Builder $query, array $attributeRow, ?string $search, int $limit): array
    {
        return $this->attributeColumns->distinctValues($query, $attributeRow, $search, $limit);
    }

    /**
     * The effective attributes of the selected categories, deduplicated by code (spec 0374, D-10).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function categoryAttributes(): Collection
    {
        return $this->categoryAttributesMemo ??= $this->attributeColumns->forCategories($this->attributeCategories);
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    private function useAttributeCategories(array $categoryIds, bool $union): void
    {
        $this->allowListUnion = $union;
        $this->attributeCategories = $categoryIds;
        $this->categoryAttributesMemo = null;
    }

    /** @return Collection<int, array<string, mixed>> the union for rows, export and persistence, the categories' own for the config */
    private function allowListedAttributes(): Collection
    {
        return $this->allowListUnion ? $this->attributeColumns->union() : $this->categoryAttributes();
    }

    /** @return Collection<int, array<string, mixed>> the set a filter, sort or distinct request resolves its column in */
    private function lookupAttributes(): Collection
    {
        return $this->lookupInUnion ? $this->attributeColumns->union() : $this->categoryAttributes();
    }

    private function isAttributeColumn(string $columnId): bool
    {
        return $this->attributeColumns->codeFor($columnId) !== null;
    }
}
