<?php

namespace App\Tables;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\ProductCategoryService;
use App\Tables\ProductCategories\ProductCategoryColumnCatalog;
use App\Tables\ProductCategories\ProductCategoryCountColumn;
use App\Tables\Shared\BusinessFunctionColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `product-categories` domain (spec 0017 REV): the
 * AG Grid SSRM list view, alongside the existing dedicated tree view
 * (GET /product-categories/tree, unchanged — still used by the category/
 * product forms' parent/category pickers).
 *
 * Real columns (name, description, created_at) are handled entirely by the
 * generic engine. `parent` is DERIVED (no real DB column — the related
 * parent's name), mirroring BusinessFunctionsTableDefinition's `manager`.
 * `attributes_count`/`products_count` are AGGREGATE columns (withCount(), no
 * real DB column either); their filter/distinct-values resolution is
 * delegated to ProductCategoryCountColumn. `attributes`/`products` are extra
 * row keys (not columns — no allow-list/sort/filter entry) feeding the
 * frontend tooltip that lists the names behind each counter.
 */
class ProductCategoriesTableDefinition extends AbstractTableDefinition
{
    /**
     * Maximum number of names honoured in the `parent` set filter. Caps the
     * WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * Maximum number of product names carried in a row's `products` list
     * (the tooltip content). `products_count` always reflects the REAL
     * total (withCount) even when the list itself is capped — the frontend
     * renders "+N more" from the difference.
     */
    private const int PRODUCT_TOOLTIP_LIST_LIMIT = 100;

    public function __construct(
        private readonly ProductCategoryService $service,
        private readonly ProductCategoryCountColumn $countColumn,
        private readonly BusinessFunctionColumn $businessFunctionColumn,
    ) {}

    public function domain(): string
    {
        return 'product-categories';
    }

    /**
     * @return class-string<ProductCategory>
     */
    public function modelClass(): string
    {
        return ProductCategory::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives ProductCategoryPolicy::
    // viewAny from modelClass() (product-categories.viewAny).

    /**
     * @return Builder<ProductCategory>
     */
    public function baseQuery(): Builder
    {
        // Eager-load parent + the lightweight id/name projections feeding the
        // attributes/products tooltip (no N+1), plus withCount for the two
        // aggregate columns (the real totals, independent of any list cap).
        // A plain id,name select over the page's (<=25) categories is cheap;
        // products is capped in mapRow(), never here, so products_count stays
        // the true total. `category_id` must be listed explicitly on the
        // HasMany `products` restricted select — unlike a BelongsToMany
        // (`attributes`, whose pivot join carries its own keys), Eloquent
        // does NOT auto-append a HasMany's foreign key to a partial select,
        // so hydration would silently return an empty collection without it.
        return ProductCategory::query()
            ->with(['parent', 'attributes:id,name', 'products:id,name,category_id'])
            ->withCount(['attributes', 'products']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return ProductCategoryColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return ProductCategoryColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return ProductCategoryColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'created_at', 'direction' => 'desc'],
        ];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * Map a ProductCategory to the row payload. `actions` is attached by the
     * generic TableService via actionsFor(). `attributes`/`products` are
     * name lists feeding the frontend tooltip for their respective `*_count`
     * column — never sortable/filterable themselves, no allow-list entry.
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var ProductCategory $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'parent' => $this->parentSummary($row->parent),
            'description' => $row->description,
            'business_function' => $this->businessFunctionColumn->nameFor($row->id),
            'requires_quote' => (bool) $row->requires_quote,
            'is_selectable' => (bool) $row->is_selectable,
            'attributes_count' => (int) $row->attributes_count,
            'products_count' => (int) $row->products_count,
            // The category's OWN assigned attributes — the exact set counted
            // by attributes_count, never inherited. Few rows, loaded in full.
            'attributes' => $row->attributes->map(static fn (Attribute $attribute): array => [
                'id' => $attribute->id,
                'name' => $attribute->name,
            ])->all(),
            // Capped tooltip list; products_count above stays the true total.
            'products' => $row->products->take(self::PRODUCT_TOOLTIP_LIST_LIMIT)
                ->map(static fn (Product $product): array => ['id' => $product->id, 'name' => $product->name])
                ->all(),
            'created_at' => $row->created_at,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function parentSummary(?ProductCategory $parent): ?array
    {
        if ($parent === null) {
            return null;
        }

        return ['id' => $parent->id, 'name' => $parent->name];
    }

    /**
     * Allowed action keys for a single row, via ProductCategoryPolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'layout';
        }

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Handle the derived `parent` set filter and the aggregate `*_count`
     * number filters. Every other column id (the real columns) falls
     * through to the generic engine.
     *
     * @param  Builder<ProductCategory>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return match ($columnId) {
            'parent' => $this->filterByParentName($query, $filter),
            'business_function' => $this->filterByBusinessFunctionName($query, $filter),
            'attributes_count' => $this->countColumn->applyDerivedFilter($query, 'attributes', $filter),
            'products_count' => $this->countColumn->applyDerivedFilter($query, 'products', $filter),
            default => false,
        };
    }

    /**
     * Derived set filter on the EFFECTIVE business function name (spec
     * 0023), delegated to BusinessFunctionColumn (the category IS the row,
     * so this filters on `id`).
     *
     * @param  Builder<ProductCategory>  $query
     * @param  array<string, mixed>  $filter
     */
    private function filterByBusinessFunctionName(Builder $query, array $filter): bool
    {
        $this->businessFunctionColumn->applyCategoryIdFilter($query, $filter);

        return true;
    }

    /**
     * Derived set filter via whereHas on the self-referencing `parent`
     * relation, matched by name. Bound parameters, capped cardinality.
     *
     * @param  Builder<ProductCategory>  $query
     * @param  array<string, mixed>  $filter
     */
    private function filterByParentName(Builder $query, array $filter): bool
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return true;
        }

        $names = array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);

        if ($names !== []) {
            $query->whereHas('parent', static function (Builder $relatedQuery) use ($names): void {
                $relatedQuery->whereIn('name', $names);
            });
        }

        return true;
    }

    /**
     * ORDER BY the parent's name via a correlated subquery, so sorting never
     * needs a row-multiplying JOIN on the main query.
     *
     * @param  Builder<ProductCategory>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId !== 'parent') {
            return false;
        }

        // Self-join: the subquery's own table is aliased (`parent_category`)
        // so it never collides with the outer query's `product_categories`.
        $subquery = ProductCategory::query()
            ->from('product_categories as parent_category')
            ->select('parent_category.name')
            ->whereColumn('parent_category.id', 'product_categories.parent_id')
            ->limit(1);

        $query->orderBy($subquery, $direction);

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for the derived `parent`
     * column and the aggregate `*_count` columns.
     *
     * @param  Builder<ProductCategory>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return match ($columnId) {
            'parent' => $this->distinctParentNames($query, $search, $limit),
            'business_function' => $this->businessFunctionColumn->distinctValues((clone $query)->pluck('id'), $search, $limit),
            'attributes_count' => $this->countColumn->distinctValues($query, 'attributes_count', $search, $limit),
            'products_count' => $this->countColumn->distinctValues($query, 'products_count', $search, $limit),
            default => null,
        };
    }

    /**
     * @param  Builder<ProductCategory>  $query
     * @return array<int, string>
     */
    private function distinctParentNames(Builder $query, ?string $search, int $limit): array
    {
        $parentIds = (clone $query)->whereNotNull('parent_id')->select('parent_id');

        return DB::table('product_categories')
            ->whereIn('id', $parentIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Delegate to ProductCategoryService::delete() so the generic bulk-delete
     * endpoint (newly reachable now that this domain is registered) respects
     * the exact same restrictive-delete guard (children/products in use) as
     * the single DELETE /product-categories/{productCategory} endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var ProductCategory $model */
        $this->service->delete($model);
    }
}
