<?php

namespace App\Tables;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\State;
use App\Models\User;
use App\Support\Geo\GeoNameLocalizer;
use App\Tables\Products\ProductColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `products` domain (spec 0017).
 *
 * Real columns (name, description, cost, price, created_at) are handled
 * entirely by the generic engine. `category` has no real DB column of its
 * own (it is the related category's name) and is DERIVED: its set
 * filter/sort/distinct-values are resolved here against the related
 * category's name, mirroring BusinessFunctionsTableDefinition's `manager`
 * derived column. `state` (the Regione) is the same DERIVED shape, but is
 * geo reference data: its rendered name, set-filter matching and distinct
 * values are localized to Italian via GeoNameLocalizer, mirroring
 * ProjectsTableDefinition's GEO_COLUMN_IDS handling — the DB column itself
 * stays English. No dynamic attribute is ever a column (spec 0017 decision).
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
        // Eager-load category/state to avoid N+1 when every row projects them.
        return Product::query()->with(['category', 'state']);
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
            'name' => $row->name,
            'description' => $row->description,
            'cost' => $row->cost === null ? null : (float) $row->cost,
            'price' => $row->price === null ? null : (float) $row->price,
            'category' => $this->categorySummary($row->category),
            'state' => $this->stateSummary($row->state),
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
     * The Regione's Italian DISPLAY name (geo reference data), mirroring
     * ProjectsTableDefinition's summarize($related, geo: true).
     *
     * @return array{id: int, name: string}|null
     */
    private function stateSummary(?State $state): ?array
    {
        if ($state === null) {
            return null;
        }

        return ['id' => $state->id, 'name' => GeoNameLocalizer::toItalian($state->name)];
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
     * Handle the derived `category`/`state` set filters. Every other column
     * id (the real columns) falls through to the generic engine.
     *
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId === 'state') {
            $this->applyStateFilter($query, $filter);

            return true;
        }

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
     * The `state` set filter: values arrive as Italian display names (geo
     * reference data), matched against `states.name` (English) via
     * GeoNameLocalizer::filterMatchNames — mirrors ProjectsTableDefinition's
     * GEO_COLUMN_IDS handling.
     *
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $filter
     */
    private function applyStateFilter(Builder $query, array $filter): void
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return;
        }

        $names = array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);

        if ($names === []) {
            return;
        }

        $names = GeoNameLocalizer::filterMatchNames($names);

        $query->whereHas('state', static function (Builder $relatedQuery) use ($names): void {
            $relatedQuery->whereIn('name', $names);
        });
    }

    /**
     * ORDER BY the category's/state's name via a correlated subquery, so
     * sorting never needs a row-multiplying JOIN on the main query.
     *
     * @param  Builder<Product>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId === 'state') {
            $subquery = State::query()
                ->select('name')
                ->whereColumn('states.id', 'products.state_id')
                ->limit(1);

            $query->orderBy($subquery, $direction);

            return true;
        }

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
     * `category`/`state` columns: distinct related NAMES among the products
     * matching `$query` (already scoped by every OTHER active filter).
     *
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return match ($columnId) {
            'category' => $this->distinctCategoryNames($search, $query, $limit),
            'state' => $this->distinctStateNames($search, $query, $limit),
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
     * Distinct related state (Regione) names among the products matching
     * `$query`, localized to Italian: the distinct ENGLISH names in use are
     * translated, then filtered by the (Italian) search term, sorted and
     * capped — mirrors ProjectsTableDefinition::geoDistinctValues.
     *
     * @param  Builder<Product>  $query
     * @return array<int, string>
     */
    private function distinctStateNames(?string $search, Builder $query, int $limit): array
    {
        $stateIds = (clone $query)->whereNotNull('products.state_id')->select('products.state_id');

        $localized = DB::table('states')
            ->whereIn('id', $stateIds)
            ->distinct()
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) GeoNameLocalizer::toItalian((string) $name));

        if ($search !== null && $search !== '') {
            $localized = $localized->filter(static fn (string $name): bool => stripos($name, $search) !== false);
        }

        return $localized->sort()->values()->take($limit)->all();
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
