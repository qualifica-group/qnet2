<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\ProductCategoryService;
use App\Tables\CustomFields\DelegatesUnaugmentedTableMethods;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Decorator that scopes the `request-management` domain to a single product
 * category (spec 0064's category tab strip) and relabels the `operator_ga2`
 * column for it (spec 0080) — spec 0084's successor to the removed
 * `AttributeScopedTableDefinition`, stripped of everything that decorator
 * did around `attr.*` dynamic columns (D-1: the "Informazioni aggiuntive"
 * section moved to the Offerta): no column injection, no SSRM allow-list
 * widening (`scopeToAllProductCategories()` is GONE — the native columns'
 * sortable/filterable shape never varied by category to begin with), no
 * value read/write. What is left is TWO concerns only: WHICH rows the tab
 * shows (`baseQuery()`), and WHAT the `operator_ga2` column is called
 * (`resolveConfig()`).
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
    use DelegatesUnaugmentedTableMethods;

    /** The pre-existing `operator_ga2` column's id — never changes, only its `label` does. */
    private const string OPERATOR_COLUMN_ID = 'operator_ga2';

    private ?int $categoryScope = null;

    public function __construct(
        private readonly TableDefinition $inner,
        private readonly ProductCategoryService $productCategoryService,
    ) {}

    /**
     * Narrows `baseQuery()`/the GA2 relabel to ONE product category's rows
     * (null = D-3 "Tutte", every row).
     */
    public function scopeToProductCategory(?int $productCategoryId): void
    {
        $this->categoryScope = $productCategoryId;
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

        // D-2: EXISTS on the opportunity's OWN product lines — a request
        // with lines on several categories is meant to appear in every
        // matching tab, never deduped here.
        $query->whereHas('productLines', static function (Builder $relatedQuery) use ($categoryScope): void {
            $relatedQuery->where('product_category_id', $categoryScope);
        });

        return $query;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return $this->inner->columns();
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        return $this->inner->mapRow($actor, $row);
    }

    public function sortableColumnIds(): array
    {
        return $this->inner->sortableColumnIds();
    }

    public function filterableColumnIds(): array
    {
        return $this->inner->filterableColumnIds();
    }

    public function searchableColumnIds(): array
    {
        return $this->inner->searchableColumnIds();
    }

    /**
     * Spec 0080: rewrites the pre-existing `operator_ga2` column's `label`
     * to the scoped category's level-2 "Gestore Account" RAW TEXT when one
     * is configured — every other column, and every property of this one,
     * stay untouched. The "Tutte" tab (`categoryScope` null) or a category
     * with no level-2 label leaves the column exactly as it is today (the
     * `requestManagement.columns.operator` i18n key).
     *
     * @return array<string, mixed>
     */
    public function resolveConfig(User $actor): array
    {
        $config = $this->inner->resolveConfig($actor);
        $config['columns'] = $this->relabelOperatorColumn($config['columns']);

        return $config;
    }

    /**
     * @return array<string, array{visible: bool, width: int|null, order: int}>
     */
    public function defaultColumnLayout(): array
    {
        return $this->inner->defaultColumnLayout();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function filterableColumnMap(): array
    {
        return $this->inner->filterableColumnMap();
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return $this->inner->applyDerivedFilter($query, $columnId, $columnConfig, $filter);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        return $this->inner->applyDerivedSort($query, $columnId, $direction);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return $this->inner->distinctValues($actor, $columnId, $columnConfig, $search, $query, $limit);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        return $this->inner->applyDerivedSearch($query, $columnId, $pattern);
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, array<string, mixed>>
     */
    private function relabelOperatorColumn(array $columns): array
    {
        $label = $this->scopedOperatorLabel();

        if ($label === null) {
            return $columns;
        }

        return array_map(function (array $column) use ($label): array {
            if (($column['id'] ?? null) === self::OPERATOR_COLUMN_ID) {
                $column['label'] = $label;
            }

            return $column;
        }, $columns);
    }

    /**
     * The scoped category's EFFECTIVE label for
     * `Opportunity::OPERATOR_MANAGER_POSITION` (GA2), or null when there is
     * no scope, the category no longer exists, or it defines no label for
     * that position.
     */
    private function scopedOperatorLabel(): ?string
    {
        if ($this->categoryScope === null) {
            return null;
        }

        $category = ProductCategory::find($this->categoryScope);

        if ($category === null) {
            return null;
        }

        return $this->productCategoryService->effectiveManagerLabels($category)[Opportunity::OPERATOR_MANAGER_POSITION] ?? null;
    }
}
