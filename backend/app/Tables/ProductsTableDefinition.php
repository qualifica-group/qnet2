<?php

namespace App\Tables;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTypology;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Tables\Products\ProductColumnCatalog;
use App\Tables\Products\ProductRelationColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `products` domain (spec 0017; `code` per spec
 * 0065, AC-009b).
 *
 * Real columns (code, name, description, cost, price, created_at) are handled
 * entirely by the generic engine. `category` and `unit_of_measure` (spec
 * 0088) have no real DB column of their own (they are the related lookup's
 * name) and are DERIVED: their set filter/sort/distinct-values are resolved
 * here against the related name, mirroring BusinessFunctionsTableDefinition's
 * `manager` derived column. No dynamic attribute is ever a column (spec 0017
 * decision).
 */
class ProductsTableDefinition extends AbstractTableDefinition
{
    public function __construct(
        private readonly ProductRelationColumns $relationColumns,
    ) {}

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
        // Eager-load the derived relations to avoid N+1 when every row
        // projects them.
        return Product::query()->with(['category', 'unitOfMeasure', 'productTypology']);
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
            'unit_of_measure' => $this->unitOfMeasureSummary($row->unitOfMeasure),
            'product_typology' => $this->productTypologySummary($row->productTypology),
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
     * Mirrors ProductResource's unit summary shape, so the grid cell and the
     * detail view read the same fields.
     *
     * @return array{id: int, name: string, symbol: string}|null
     */
    private function unitOfMeasureSummary(?UnitOfMeasure $unitOfMeasure): ?array
    {
        if ($unitOfMeasure === null) {
            return null;
        }

        return ['id' => $unitOfMeasure->id, 'name' => $unitOfMeasure->name, 'symbol' => $unitOfMeasure->symbol];
    }

    /**
     * Mirrors ProductResource's typology summary shape (spec 0099), so the
     * grid cell and the detail view read the same fields.
     *
     * @return array{id: int, name: string}|null
     */
    private function productTypologySummary(?ProductTypology $productTypology): ?array
    {
        if ($productTypology === null) {
            return null;
        }

        return ['id' => $productTypology->id, 'name' => $productTypology->name];
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
     * Handle the set filter of the derived relation columns
     * (`category`, `unit_of_measure`). Every other column id (the real
     * columns) falls through to the generic engine.
     *
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return $this->relationColumns->applyFilter($query, $columnId, $filter);
    }

    /**
     * ORDER BY the related name via a correlated subquery, so sorting never
     * needs a row-multiplying JOIN on the main query.
     *
     * @param  Builder<Product>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        return $this->relationColumns->applySort($query, $columnId, $direction);
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for the derived related-name
     * columns: distinct related NAMES among the products matching `$query`
     * (already scoped by every OTHER active filter).
     *
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId === 'product_type') {
            return $this->distinctProductTypes($search, $query, $limit);
        }

        return $this->relationColumns->distinctValues($columnId, $search, $query, $limit);
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
