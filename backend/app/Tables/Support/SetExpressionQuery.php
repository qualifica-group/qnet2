<?php

namespace App\Tables\Support;

use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Set Filter and distinct values over an SQL expression of a joined table
 * (the generic engine only knows unqualified real columns). The expression is
 * a constant of the calling table's SQL class, never request input; the
 * values travel as bound parameters.
 */
final class SetExpressionQuery
{
    private const int MAX_VALUES = 200;

    /**
     * Narrow $query to the rows whose expression is one of the ticked values
     * (a ticked blank entry matches NULL).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public static function apply(Builder $query, string $expression, array $filter): void
    {
        $raw = is_array($filter['values'] ?? null) ? $filter['values'] : [];
        $values = array_slice(array_values(array_filter($raw, static fn (mixed $value): bool => is_string($value) || is_int($value))), 0, self::MAX_VALUES);
        $matchesBlank = in_array(null, $raw, true);

        if ($values === [] && ! $matchesBlank) {
            return;
        }

        $column = DB::raw($expression);

        $query->where(static function (Builder $group) use ($column, $values, $matchesBlank): void {
            if ($values !== []) {
                $group->whereIn($column, array_map('strval', $values));
            }

            if ($matchesBlank) {
                $group->orWhereNull($column);
            }
        });
    }

    /**
     * Distinct non-null values of the expression over the filtered rows, plus
     * the blank entry (null) first when some rows have none and no search is active.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string|null>
     */
    public static function distinct(Builder $query, string $expression, ?string $search, int $limit): array
    {
        $hasSearch = $search !== null && $search !== '';

        $values = self::plain($query)
            ->selectRaw("{$expression} as value")
            ->whereRaw("{$expression} IS NOT NULL")
            ->when($hasSearch, fn (QueryBuilder $builder) => $builder->whereRaw("{$expression} LIKE ?", ['%'.app(FilterApplier::class)->escapeLike((string) $search).'%']))
            ->distinct()
            ->orderBy('value')
            ->limit($limit)
            ->pluck('value')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();

        // The blank entry ("(Vuoti)") matches no search term.
        if (! $hasSearch && self::plain($query)->selectRaw('1')->whereRaw("{$expression} IS NULL")->exists()) {
            array_unshift($values, null);
        }

        return $values;
    }

    /**
     * @param  Builder<Model>  $query
     */
    private static function plain(Builder $query): QueryBuilder
    {
        return (clone $query)->reorder()->toBase()->select([]);
    }
}
