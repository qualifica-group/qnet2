<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use Illuminate\Database\Eloquent\Builder;

/**
 * Aggregates an indicator's already-filtered query into the TOTAL scalar and
 * the per-GA2 breakdown (spec 0106, D-10): total() runs its OWN aggregation
 * over the whole branch (never a sum of the byOperator() rows — the brief's
 * own requirement), while byOperator() groups by `quotes.operator_id`, the
 * single column that partitions the branch's distinct requests (a request
 * with no operator becomes its own "Non assegnato" group for free, via SQL
 * `GROUP BY` on a nullable column).
 *
 * `$countExpression` is always a small, hard-coded literal passed by the
 * calling indicator ('notes.id', 'quotes.id', 'distinct quotes.id',
 * 'distinct registries.id') — never user input, the same
 * `selectRaw('count(distinct quotes.id) as requests_count')` idiom already
 * used by RequestCategoryTabsResolver for the identical need (backend.md
 * §8: the SQL-injection sink is raw SQL built from INPUT, not a static
 * aggregate expression).
 */
final class QuoteCountAggregator
{
    public function total(Builder $query, string $countExpression): int
    {
        return (int) $query->selectRaw("count({$countExpression}) as aggregate")->value('aggregate');
    }

    /**
     * @return array<int, array{operator_id: int|null, value: int}>
     */
    public function byOperator(Builder $query, string $countExpression): array
    {
        return $query->select('quotes.operator_id')
            ->selectRaw("count({$countExpression}) as aggregate")
            ->groupBy('quotes.operator_id')
            ->get()
            ->map(static fn ($row): array => [
                'operator_id' => $row->operator_id === null ? null : (int) $row->operator_id,
                'value' => (int) $row->aggregate,
            ])
            ->all();
    }
}
