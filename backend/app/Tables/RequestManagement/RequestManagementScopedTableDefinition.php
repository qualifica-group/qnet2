<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Models\Quote;
use App\Models\User;
use App\Products\ProductCategoryBranches;
use App\Services\RequestManagement\RequestManagementService;
use App\Tables\CategoryTabs\AttributeColumnsScopable;
use App\Tables\CustomFields\DelegatesUnaugmentedTableMethods;
use App\Tables\ProductCategoryScopable;
use App\Tables\RequestManagement\Concerns\WritesAttributeCells;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Decorator that scopes the `request-management`/`enrollee-management`
 * domains (spec 0130: the SAME decorator, `$inner->domain()` tells it which)
 * to a single product category (spec 0064's category tab strip), appends that
 * category's `attr.<code>` flexible columns, and shapes its Gestore Account
 * position columns for it (spec 0382, RequestManagerPositionLayout).
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
 * and WHICH Gestore Account columns it shows and under what name
 * (`resolveConfig()`/`defaultColumnLayout()`).
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
 * the preferences default baseline. One exception: the default VISIBILITY of
 * the Gestore Account position columns follows the tab (spec 0382 D-8).
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
class RequestManagementScopedTableDefinition implements AttributeColumnsScopable, ProductCategoryScopable, TableDefinition
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

    private const string PRODUCT_LINES_TABLE = 'opportunity_product_lines';

    private ?int $categoryScope = null;

    /**
     * A root tab leaves `categoryScope` null: it shows the root's own
     * `attr.*` columns (spec 0350 D-4, rev.3) and names the Gestore Account
     * columns with the root's labels (spec 0382 D-3).
     */
    private ?int $branchScope = null;

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
        private readonly AttributeGridColumns $attributeColumns,
        private readonly RequestManagementService $service,
        private readonly ProductCategoryBranches $branches,
        private readonly RequestManagerPositionLayout $positionLayout,
    ) {}

    /**
     * Narrows `baseQuery()`, the `attr.*` columns and the G.A. labels to ONE
     * product category (null = D-3 "Tutte", every row and zero `attr.*`
     * columns).
     */
    public function scopeToProductCategory(?int $productCategoryId): void
    {
        $this->categoryScope = $productCategoryId;
    }

    /**
     * Narrows `baseQuery()` to the rows of a root category's branch (spec
     * 0337), the `attr.*` columns and the G.A. labels to the root's own set.
     */
    public function scopeToProductCategoryBranch(?int $rootId): void
    {
        $this->branchScope = $rootId;
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

        $categoryIds = $this->scopedCategoryIds();

        if ($categoryIds === null) {
            return $query;
        }

        // D-2: the offer's opportunity's OWN product lines (spec 0086: `quotes`
        // carries no product lines of its own) — a request with lines on
        // several categories is meant to appear in every matching tab, never
        // deduped here. An IN-subquery rather than `whereHas`: the nested
        // EXISTS made MariaDB walk the whole `quotes` index for the count
        // (2.7 s on 1M rows), the IN starts from the category's own index
        // (0.17 s) — stress test 2026-09-29, S4.
        $query->whereIn(
            $query->qualifyColumn('opportunity_id'),
            static fn (QueryBuilder $lines) => $lines
                ->select('opportunity_id')
                ->from(self::PRODUCT_LINES_TABLE)
                ->whereIn('product_category_id', $categoryIds),
        );

        return $query;
    }

    /**
     * @return array<int, int>|null
     */
    private function scopedCategoryIds(): ?array
    {
        if ($this->branchScope !== null) {
            return $this->branches->branchIds($this->branchScope);
        }

        return $this->categoryScope === null ? null : [$this->categoryScope];
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
     * Spec 0382: the Gestore Account position columns are trimmed, named
     * and shown per tab (RequestManagerPositionLayout); every other column
     * stays untouched.
     *
     * @return array<string, mixed>
     */
    public function resolveConfig(User $actor): array
    {
        $config = $this->inner->resolveConfig($actor);
        $config['columns'] = $this->positionLayout->shape($config['columns'], $this->labelCategoryId());
        $attributes = $this->categoryAttributes();

        if ($attributes->isEmpty()) {
            return $config;
        }

        $config['columns'] = [
            ...$config['columns'],
            ...$this->attributeColumns->resolvedColumns(
                $attributes,
                // The last native order, not the count: the position columns
                // trimmed by the layout leave gaps (spec 0382).
                (int) max([0, ...array_column($config['columns'], 'order')]),
                $this->attributeColumns->valuesEditable($actor, $this->inner->domain()),
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

        // Spec 0382 D-8: the position columns start shown or hidden per tab,
        // so a choice saved in one tab is a delta of THAT tab's default.
        foreach ($this->positionLayout->visibility($this->labelCategoryId()) as $columnId => $visible) {
            if (isset($layout[$columnId])) {
                $layout[$columnId]['visible'] = $visible;
            }
        }

        $attributes = $this->attributeColumns->union();

        if ($attributes->isEmpty()) {
            return $layout;
        }

        $order = count($layout);

        // Same baseline a tab shows (AttributeColumnBuilder::resolved(): visible,
        // no width): a hidden attribute column must differ from its default,
        // or the preferences delta drops it and the column reappears on reload.
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
     * The effective attributes of the scoped category or root ("Tutte" -> none).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function categoryAttributes(): Collection
    {
        return $this->attributeColumns->forCategory($this->categoryScope ?? $this->branchScope);
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
     * The category whose labels name the Gestore Account columns (spec 0382
     * D-3): the open tab, or the root of a root-level tab (spec 0337).
     */
    private function labelCategoryId(): ?int
    {
        return $this->categoryScope ?? $this->branchScope;
    }
}
