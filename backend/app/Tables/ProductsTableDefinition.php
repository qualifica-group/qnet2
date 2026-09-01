<?php

namespace App\Tables;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Tables\Products\ProductColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `products` domain (spec 0017; `code` per spec
 * 0065, AC-009b).
 *
 * Real columns (code, name, description, cost, price, created_at) are handled
 * entirely by the generic engine. `category` has no real DB column of its
 * own (it is the related category's name) and is DERIVED: its set
 * filter/sort/distinct-values are resolved here against the related
 * category's name, mirroring BusinessFunctionsTableDefinition's `manager`
 * derived column. No dynamic attribute is ever a column (spec 0017 decision).
 */
class ProductsTableDefinition extends AbstractTableDefinition
{
    /**
     * Maximum number of names honoured in the `category` set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    public function domain(): string
    {
        return 'products';
    }

    /**
     * @return class-string<Product>
     */
    public function modelClass(): string
    {
        return Product::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives ProductPolicy::viewAny from
    // modelClass() (products.viewAny).

    /**
     * @return Builder<Product>
     */
    public function baseQuery(): Builder
    {
        // Eager-load the category to avoid N+1 when every row projects it.
        return Product::query()->with(['category']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return ProductColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return ProductColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return ProductColumnCatalog::actions();
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
     * Badge metadata for the `product_type` column, driven by ProductType.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        if ($columnId !== 'product_type') {
            return null;
        }

        return array_map(static fn ($meta): array => $meta->toArray(), ProductType::options());
    }

    /**
     * The `product_type` badge is driven by ProductType, exposed to the
     * frontend config under the `product_type` enum key (config/config.php
     * form_enums), so the client can localize the badge label.
     */
    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return $columnId === 'product_type' ? 'product_type' : null;
    }

    /**
     * Map a Product to the row payload. `actions` is attached by the generic
     * TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Product $row */
        return [
            'id' => $row->id,
            'code' => $row->code,
            'name' => $row->name,
            'description' => $row->description,
            'cost' => $row->cost === null ? null : (float) $row->cost,
            'price' => $row->price === null ? null : (float) $row->price,
            'category' => $this->categorySummary($row->category),
            'product_type' => $row->product_type,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function categorySummary(?ProductCategory $category): ?array
    {
        if ($category === null) {
            return null;
        }

        return ['id' => $category->id, 'name' => $category->name];
    }

    /**
     * Allowed action keys for a single row, via ProductPolicy.
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

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Handle the derived `category` set filter. Every other column id (the
     * real columns) falls through to the generic engine.
     *
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId !== 'category') {
            return false;
        }

        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return true;
        }

        $names = array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);

        if ($names !== []) {
            $query->whereHas('category', static function (Builder $relatedQuery) use ($names): void {
                $relatedQuery->whereIn('name', $names);
            });
        }

        return true;
    }

    /**
     * ORDER BY the category's name via a correlated subquery, so sorting
     * never needs a row-multiplying JOIN on the main query.
     *
     * @param  Builder<Product>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId !== 'category') {
            return false;
        }

        $subquery = ProductCategory::query()
            ->select('name')
            ->whereColumn('product_categories.id', 'products.category_id')
            ->limit(1);

        $query->orderBy($subquery, $direction);

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for the derived
     * `category` column: distinct related NAMES among the products matching
     * `$query` (already scoped by every OTHER active filter).
     *
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return match ($columnId) {
            'category' => $this->distinctCategoryNames($search, $query, $limit),
            'product_type' => $this->distinctProductTypes($search, $query, $limit),
            default => null,
        };
    }

    /**
     * Distinct related category NAMES among the products matching `$query`.
     *
     * @param  Builder<Product>  $query
     * @return array<int, string>
     */
    private function distinctCategoryNames(?string $search, Builder $query, int $limit): array
    {
        $categoryIds = (clone $query)->select('products.category_id');

        return DB::table('product_categories')
            ->whereIn('id', $categoryIds)
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
     * Distinct `product_type` values among the products matching `$query`, via
     * a plain DB::table query on the raw column (never through Eloquent, which
     * would hydrate the ProductType cast and fail to stringify it).
     *
     * @param  Builder<Product>  $query
     * @return array<int, string>
     */
    private function distinctProductTypes(?string $search, Builder $query, int $limit): array
    {
        $productIds = (clone $query)->select('products.id');

        return DB::table('products')
            ->whereIn('id', $productIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('product_type', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('product_type')
            ->limit($limit)
            ->pluck('product_type')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
