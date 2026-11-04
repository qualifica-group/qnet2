<?php

declare(strict_types=1);

namespace App\Tables\CategoryTabs\Concerns;

use App\Models\User;
use App\Tables\RequestManagement\AttributeGridColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The READ side of the `attr.<code>` flexible columns for a category-tab
 * decorator (spec 0350, D-7): the columns of the scoped category's effective
 * attribute set, appended after the native ones, with their row values and
 * filter/sort/distinct hooks. Mirrors what
 * `RequestManagementScopedTableDefinition` does for Gestione Richieste, on top
 * of the shared `AttributeGridColumns`.
 *
 * The using class must expose `private readonly TableDefinition $inner`,
 * `private readonly AttributeGridColumns $attributeColumns` (already bound to
 * its `AttributeGridTarget`) and `private ?int $categoryScope`. The "Tutte"
 * tab and a root (branch) tab leave `categoryScope` null, hence no `attr.*`
 * (D-4). Where the values live, how they are filtered and whether they are
 * editable are protected hooks with the own-column default; the contracts grid
 * overrides them to read its source offer's JSON (D-3, D-8).
 *
 * Every `code` reaching a query comes from the server-resolved set
 * (allow-list), never from request input (backend.md §8).
 */
trait AppendsCategoryAttributeColumns
{
    /** Widens the SSRM allow-lists to the union of every category (D-4, spec 0064). */
    private bool $allowListUnion = false;

    /**
     * Set by the write trait right after a successful `attr.<code>` write so
     * the immediately following `mapRow()` (the PATCH response remap) shows
     * the written cell: the PATCH carries no category scope of its own.
     *
     * @var Collection<int, array<string, mixed>>|null
     */
    private ?Collection $rowAttributesOverride = null;

    public function scopeToAllProductCategories(): void
    {
        $this->allowListUnion = true;
    }

    /**
     * UNSCOPED by design: the union's raw declarations, the shape
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

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
    public function resolveConfig(User $actor): array
    {
        $config = $this->inner->resolveConfig($actor);
        $attributes = $this->categoryAttributes();

        if ($attributes->isEmpty()) {
            return $config;
        }

        $config['columns'] = [
            ...$config['columns'],
            ...$this->attributeColumns->resolvedColumns($attributes, count($config['columns']), $this->attributeValuesEditable($actor)),
        ];

        return $config;
    }

    /**
     * @return array<string, array{visible: bool, width: int|null, order: int}>
     */
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

    /**
     * @return array<string, array<string, mixed>>
     */
    public function filterableColumnMap(): array
    {
        $map = $this->inner->filterableColumnMap();

        foreach ($this->attributeColumns->rawColumns($this->allowListedAttributes()) as $column) {
            $map[$column['id']] = $column;
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
        $attributeRow = $this->attributeColumns->attributeRowFor($columnId, $this->categoryAttributes());

        if ($attributeRow === null) {
            return $this->inner->applyDerivedFilter($query, $columnId, $columnConfig, $filter);
        }

        $this->applyAttributeFilter($query, $attributeRow, $filter);

        return true;
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        $attributeRow = $this->attributeColumns->attributeRowFor($columnId, $this->categoryAttributes());

        if ($attributeRow === null) {
            return $this->inner->applyDerivedSort($query, $columnId, $direction);
        }

        $this->applyAttributeSort($query, $attributeRow, $direction);

        return true;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        $attributeRow = $this->attributeColumns->attributeRowFor($columnId, $this->categoryAttributes());

        if ($attributeRow === null) {
            return $this->inner->distinctValues($actor, $columnId, $columnConfig, $search, $query, $limit);
        }

        return $this->attributeDistinctValues($query, $attributeRow, $search, $limit);
    }

    /**
     * @param  Builder<Model>  $query
     */
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

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $attributeRow
     * @param  array<string, mixed>  $filter
     */
    protected function applyAttributeFilter(Builder $query, array $attributeRow, array $filter): void
    {
        $this->attributeColumns->applyFilter($query, $attributeRow, $filter);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $attributeRow
     */
    protected function applyAttributeSort(Builder $query, array $attributeRow, string $direction): void
    {
        $this->attributeColumns->applySort($query, $attributeRow, $direction);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $attributeRow
     * @return array<int, string>
     */
    protected function attributeDistinctValues(Builder $query, array $attributeRow, ?string $search, int $limit): array
    {
        return $this->attributeColumns->distinctValues($query, $attributeRow, $search, $limit);
    }

    /**
     * The scoped category's effective attributes ("Tutte"/root tab -> none).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function categoryAttributes(): Collection
    {
        return $this->attributeColumns->forCategory($this->categoryScope);
    }

    /**
     * The set the SSRM allow-lists are built from: the union when the caller
     * asked for it (preferences/filter persistence), the tab's own otherwise.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function allowListedAttributes(): Collection
    {
        return $this->allowListUnion ? $this->attributeColumns->union() : $this->categoryAttributes();
    }
}
