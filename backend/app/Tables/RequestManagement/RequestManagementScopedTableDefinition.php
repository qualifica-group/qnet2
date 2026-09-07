<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use App\Services\ProductCategoryService;
use App\Services\RequestManagement\RequestManagementService;
use App\Tables\CustomFields\DelegatesUnaugmentedTableMethods;
use App\Tables\RequestManagement\Concerns\WritesAttributeCells;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Decorator that scopes the `request-management` domain to a single product
 * category (spec 0064's category tab strip), appends that category's
 * `attr.<code>` flexible columns, and relabels its Gestore Account columns
 * (`operator_ga2`/`manager_ga3`) for it (spec 0080, extended to GA3 by the
 * direttiva utente 2026-09-07).
 *
 * The `attr.*` half was removed by spec 0084 D-1, which moved "Informazioni
 * aggiuntive" from the Opportunity to the Offerta, and is RESTORED by the
 * user directive 2026-08-31 on the record this grid IS since spec 0086 — the
 * Offerta itself. The column shapes, the JSON storage hooks and the
 * `role_field_permissions` gate are the ones spec 0064 froze; only the
 * subject changed (`quotes.attribute_values`, `AttributeContext::Quote`), so
 * the tab's columns show exactly the set the work panel and the Offerte form
 * already resolve.
 *
 * THREE scope-driven concerns: WHICH rows the tab shows (`baseQuery()`),
 * WHICH flexible columns it carries (`resolveConfig()`/`mapRow()` and the
 * derived filter/sort/distinct hooks, delegated to AttributeGridColumns),
 * and WHAT the Gestore Account columns are called (`resolveConfig()`).
 *
 * TWO independent scope concepts, set explicitly by the caller
 * (`TableController` for the request-scoped case, the Table FormRequests for
 * their own independently-resolved definition instance):
 *  - `scopeToProductCategory(?id)` — ONE category (or none): drives the
 *    request-facing shape AND, unless `scopeToAllProductCategories()` was
 *    also called, the SSRM allow-lists.
 *  - `scopeToAllProductCategories()` — the UNION across every category
 *    (D-4): ONLY widens the SSRM allow-lists, used exclusively by the
 *    column-preferences/filter-state persistence endpoints, whose saved
 *    layout must never 422 depending on which tab was open when it was saved.
 *
 * `columns()`/`defaultColumnLayout()` are UNSCOPED by design (always the
 * union): they back `TableCellUpdateService`'s structural PATCH lookup and
 * the preferences default baseline, neither of which is a per-tab concept.
 *
 * Composed OUTSIDE `CustomFieldAwareTableDefinition` in
 * `TableRegistry::resolve()` (`request-management` is custom-fieldable,
 * mirrors `App\Tables\Quotes\OpportunityScopedTableDefinition`'s own
 * docblock reasoning for `quotes`): `$inner` is already the custom-field-
 * augmented definition, and `TableController`'s `instanceof` scoping check
 * must target THIS outermost wrap — a capability baked into the concrete
 * `RequestManagementTableDefinition` would sit one layer too deep and never
 * be reached once wrapped.
 *
 * Pure passthrough when no scope has been set (D-3's "Tutte" tab): every
 * existing caller of the `request-management` domain is byte-identical to
 * today.
 */
class RequestManagementScopedTableDefinition implements TableDefinition
{
    use DelegatesUnaugmentedTableMethods, WritesAttributeCells {
        // Both traits declare editableColumnIds()/updateCell(): the
        // passthrough from DelegatesUnaugmentedTableMethods is the WRONG one
        // here (spec 0064 §M4 augments both for `attr.*` columns) —
        // WritesAttributeCells wins, and it delegates to $this->inner itself
        // for every non-`attr.*` column, so the passthrough behaviour is
        // preserved either way.
        WritesAttributeCells::editableColumnIds insteadof DelegatesUnaugmentedTableMethods;
        WritesAttributeCells::updateCell insteadof DelegatesUnaugmentedTableMethods;
    }

    private ?int $categoryScope = null;

    private bool $allowListUnion = false;

    /**
     * Set by `WritesAttributeCells::updateCell()` right after a successful
     * `attr.<code>` write, so the SAME definition instance's immediately
     * following `mapRow()` call (TableCellUpdateService's row remap) exposes
     * `attr.*` — the PATCH endpoint carries no category-scope param of its
     * own, so the row's OWN applicable attributes stand in for it.
     *
     * @var Collection<int, array<string, mixed>>|null
     */
    private ?Collection $rowAttributesOverride = null;

    public function __construct(
        private readonly TableDefinition $inner,
        private readonly ProductCategoryService $productCategoryService,
        private readonly AttributeGridColumns $attributeColumns,
        private readonly RequestManagementService $service,
    ) {}

    /**
     * Narrows `baseQuery()`, the `attr.*` columns and the G.A. relabel to ONE
     * product category (null = D-3 "Tutte", every row and zero `attr.*`
     * columns).
     */
    public function scopeToProductCategory(?int $productCategoryId): void
    {
        $this->categoryScope = $productCategoryId;
    }

    /**
     * Widens the SSRM allow-lists (sort/filter column ids) to the UNION of
     * every category's attributes (D-4) — used by the preferences/filter
     * persistence endpoints only.
     */
    public function scopeToAllProductCategories(): void
    {
        $this->allowListUnion = true;
    }

    /**
     * @return Builder<Model>
     */
    public function baseQuery(): Builder
    {
        $query = $this->inner->baseQuery();

        if ($this->categoryScope === null) {
            return $query;
        }

        $categoryScope = $this->categoryScope;

        // D-2: EXISTS on the offer's opportunity's OWN product lines (spec
        // 0086: `quotes` carries no product lines of its own) — a request
        // with lines on several categories is meant to appear in every
        // matching tab, never deduped here.
        $query->whereHas('opportunity.productLines', static function (Builder $relatedQuery) use ($categoryScope): void {
            $relatedQuery->where('product_category_id', $categoryScope);
        });

        return $query;
    }

    /**
     * UNSCOPED by design (see class docblock): the union's RAW declarations,
     * the shape TableCellUpdateService looks a submitted `attr.<code>` up in.
     *
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        $attributes = $this->attributeColumns->union();

        if ($attributes->isEmpty()) {
            return $this->inner->columns();
        }

        return [...$this->inner->columns(), ...$this->attributeColumns->rawColumns($attributes)];
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

        /** @var Quote $row */
        return [...$mapped, ...$this->attributeColumns->rowValues($row, $attributes)];
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
     * Spec 0080 (extended to GA3, direttiva utente 2026-09-07): rewrites the
     * `label` of each Gestore Account column to the scoped category's RAW
     * TEXT for the position it names — every other column, and every other
     * property of these two, stay untouched. The "Tutte" tab
     * (`categoryScope` null), or a category defining no label for a position,
     * leaves that column on its own i18n key.
     *
     * @return array<string, mixed>
     */
    public function resolveConfig(User $actor): array
    {
        $config = $this->inner->resolveConfig($actor);
        $config['columns'] = $this->relabelManagerColumns($config['columns']);
        $attributes = $this->categoryAttributes();

        if ($attributes->isEmpty()) {
            return $config;
        }

        $config['columns'] = [
            ...$config['columns'],
            ...$this->attributeColumns->resolvedColumns(
                $attributes,
                count($config['columns']),
                $this->attributeColumns->valuesEditable($actor),
            ),
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

        if ($attributes->isEmpty()) {
            return $layout;
        }

        $order = count($layout);

        foreach ($this->attributeColumns->rawColumns($attributes) as $column) {
            $order++;
            $layout[$column['id']] = ['visible' => false, 'width' => null, 'order' => $order];
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

        $this->attributeColumns->applyFilter($query, $attributeRow, $filter);

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

        $this->attributeColumns->applySort($query, $attributeRow, $direction);

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

        return $this->attributeColumns->distinctValues($query, $attributeRow, $search, $limit);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        return $this->inner->applyDerivedSearch($query, $columnId, $pattern);
    }

    /**
     * The scoped category's effective attributes ("Tutte" -> none).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function categoryAttributes(): Collection
    {
        return $this->attributeColumns->forCategory($this->categoryScope);
    }

    /**
     * The set the SSRM allow-lists are built from: the union (D-4) when the
     * caller asked for it, the scoped category's own set otherwise.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function allowListedAttributes(): Collection
    {
        return $this->allowListUnion ? $this->attributeColumns->union() : $this->categoryAttributes();
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, array<string, mixed>>
     */
    private function relabelManagerColumns(array $columns): array
    {
        $labels = $this->scopedManagerLabels();

        if ($labels === []) {
            return $columns;
        }

        $byColumnId = [];

        foreach (RequestManagerColumns::POSITIONS as $position => $columnId) {
            if (isset($labels[$position])) {
                $byColumnId[$columnId] = $labels[$position];
            }
        }

        return array_map(static function (array $column) use ($byColumnId): array {
            $label = $byColumnId[$column['id'] ?? ''] ?? null;

            if ($label !== null) {
                $column['label'] = $label;
            }

            return $column;
        }, $columns);
    }

    /**
     * The scoped category's EFFECTIVE labels, restricted to the positions the
     * grid exposes as their own column (RequestManagerColumns::POSITIONS —
     * `ManagerPositions::OPERATOR` and `::GA3`). Empty when there is no scope,
     * the category no longer exists, or it defines no label for any of them.
     *
     * Spec 0087, D-10: the positions come from `ManagerPositions`, never the
     * `Opportunity::OPERATOR_MANAGER_POSITION` alias — the labels these
     * columns name belong to the OFFERTA's own team (D-9), not the
     * Opportunity's.
     *
     * @return array<int, string>
     */
    private function scopedManagerLabels(): array
    {
        if ($this->categoryScope === null) {
            return [];
        }

        $category = ProductCategory::find($this->categoryScope);

        if ($category === null) {
            return [];
        }

        return array_intersect_key(
            $this->productCategoryService->effectiveManagerLabels($category),
            RequestManagerColumns::POSITIONS,
        );
    }
}
