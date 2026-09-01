<?php

namespace App\Tables\Products;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The relation-derived column machinery for the `products` domain, extracted
 * out of ProductsTableDefinition (file-size split, engineering.md §6):
 * `category` (spec 0017) and `unit_of_measure` (spec 0088) have no real DB
 * column of their own, they project the related row's `name`. Each gets a
 * `whereHas` set filter (allow-listed columns only, never whereRaw on raw
 * input — backend.md §8), a correlated subquery sort and Excel-like distinct
 * values (spec 0004/0005), mirroring QuoteRelationColumns.
 */
final class ProductRelationColumns
{
    /**
     * Maximum number of names honoured in a derived-column set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * Single-hop relation-name derived columns: relation accessor, related
     * table and owning FK column, keyed by the derived column id. Both
     * related tables label their rows `name`.
     *
     * @var array<string, array{relation: string, table: string, fk: string}>
     */
    private const array DERIVED_RELATIONS = [
        'category' => ['relation' => 'category', 'table' => 'product_categories', 'fk' => 'category_id'],
        'unit_of_measure' => ['relation' => 'unitOfMeasure', 'table' => 'units_of_measure', 'fk' => 'unit_of_measure_id'],
    ];

    /**
     * Handle a derived column's `set` filter via `whereHas` on the related
     * row's name. Returns false for any other column id (falls through to
     * the generic engine).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $filter): bool
    {
        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        $values = $this->filterValues($filter);

        if ($values !== []) {
            $query->whereHas($config['relation'], static function (Builder $relatedQuery) use ($values): void {
                $relatedQuery->whereIn('name', $values);
            });
        }

        return true;
    }

    /**
     * ORDER BY the related row's name via a correlated subquery, so sorting
     * never needs a row-multiplying JOIN on the main query.
     *
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $columnId, string $direction): bool
    {
        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        $subquery = DB::table($config['table'])
            ->select('name')
            ->whereColumn("{$config['table']}.id", "products.{$config['fk']}")
            ->limit(1);

        $query->orderBy($subquery, $direction);

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005): the distinct related NAMES
     * among the products matching `$query` (already scoped by every OTHER
     * active filter). Null for a column this class does not own.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>|null
     */
    public function distinctValues(string $columnId, ?string $search, Builder $query, int $limit): ?array
    {
        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return null;
        }

        $relatedIds = (clone $query)->select("products.{$config['fk']}");

        return DB::table($config['table'])
            ->whereIn('id', $relatedIds)
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
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function filterValues(array $filter): array
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return [];
        }

        return array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
