<?php

namespace App\Tables\PurchaseRequests;

use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Filters and sort for the purchase request grids' columns that are not plain
 * columns of the grid's own table (spec 0208). Every relation, table and column
 * name comes from the callers' constant maps, never from the request; user
 * input only ever reaches the query as a bound parameter or after an
 * allow-list check.
 */
final class PurchaseRequestDerivedQuery
{
    private const int MAX_SET_VALUES = 20;

    /**
     * "contains" filter on a related column (`$target` = [relation path, column]).
     *
     * @param  Builder<Model>  $query
     * @param  array{0: string, 1: string}  $target
     * @param  array<string, mixed>  $filter
     */
    public static function applyText(Builder $query, array $target, array $filter): void
    {
        // The column menu's value list sends the picked values as a set filter.
        if (($filter['filterType'] ?? null) === 'set') {
            self::applyRelatedValues($query, $target, $filter);

            return;
        }

        $term = $filter['filter'] ?? null;

        if (! is_string($term) || trim($term) === '') {
            return;
        }

        [$relation, $column] = $target;
        $pattern = '%'.app(FilterApplier::class)->escapeLike(trim($term)).'%';

        $query->whereHas($relation, static fn (Builder $related) => $related->where($column, 'like', $pattern));
    }

    /**
     * Set filter on a column of the grid's own table (or of a relation when
     * `$relation` is given), limited to the allowed values.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $allowed
     * @param  array<string, mixed>  $filter
     */
    public static function applySet(Builder $query, ?string $relation, string $column, array $allowed, array $filter): void
    {
        $values = self::allowedValues($filter, $allowed);

        if ($values === []) {
            return;
        }

        $relation === null
            ? $query->whereIn($column, $values)
            : $query->whereHas($relation, static fn (Builder $related) => $related->whereIn($column, $values));
    }

    /**
     * ORDER BY a column of a related table, through a correlated subquery
     * (never a JOIN that could multiply rows).
     *
     * @param  Builder<Model>  $query
     */
    public static function orderByRelated(Builder $query, string $relatedTable, string $foreignKey, string $column, string $direction): void
    {
        $query->orderBy(
            DB::table($relatedTable)->select($column)->whereColumn("{$relatedTable}.id", $foreignKey),
            $direction,
        );
    }

    /**
     * Distinct values of a related column over the rows of `$query` (the
     * `/values` endpoint). `$target` is a chain of BelongsTo relations, walked
     * key by key through subqueries so no JOIN can multiply rows.
     *
     * @param  Builder<Model>  $query
     * @param  array{0: string, 1: string}  $target
     * @return array<int, string>
     */
    public static function distinctRelated(Builder $query, array $target, ?string $search, int $limit): array
    {
        [$path, $column] = $target;
        $builder = (clone $query)->reorder()->setEagerLoads([]);

        foreach (explode('.', $path) as $name) {
            $relation = $builder->getModel()->{$name}();

            if (! $relation instanceof BelongsTo) {
                return [];
            }

            $related = $relation->getRelated();
            $builder = $related->newQuery()->whereIn(
                $related->qualifyColumn($relation->getOwnerKeyName()),
                $builder->select($relation->getQualifiedForeignKeyName())->toBase(),
            );
        }

        return $builder
            ->when($search !== null && $search !== '', static function (Builder $related) use ($column, $search): void {
                $related->where($column, 'like', '%'.app(FilterApplier::class)->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy($column)
            ->limit($limit)
            ->pluck($column)
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * Value-list filter on a related column; a `null` in the list also keeps
     * the rows without the relation.
     *
     * @param  Builder<Model>  $query
     * @param  array{0: string, 1: string}  $target
     * @param  array<string, mixed>  $filter
     */
    private static function applyRelatedValues(Builder $query, array $target, array $filter): void
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values) || $values === []) {
            return;
        }

        [$relation, $column] = $target;
        $matchesBlank = in_array(null, $values, true);
        $scalars = array_slice(array_values(array_filter($values, 'is_scalar')), 0, self::MAX_SET_VALUES);

        $query->where(static function (Builder $outer) use ($relation, $column, $scalars, $matchesBlank): void {
            if ($scalars !== []) {
                $outer->whereHas($relation, static fn (Builder $related) => $related->whereIn($column, $scalars));
            }
            if ($matchesBlank) {
                $outer->orWhereDoesntHave($relation);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $filter
     * @param  array<int, string>  $allowed
     * @return array<int, string>
     */
    private static function allowedValues(array $filter, array $allowed): array
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return [];
        }

        $clean = array_filter($values, static fn (mixed $value): bool => is_string($value) && in_array($value, $allowed, true));

        return array_slice(array_values(array_unique($clean)), 0, self::MAX_SET_VALUES);
    }
}
