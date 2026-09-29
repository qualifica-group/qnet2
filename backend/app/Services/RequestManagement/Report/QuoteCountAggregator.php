<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use Illuminate\Database\Eloquent\Builder;

/**
 * Aggregates an indicator's already-filtered query into the TOTAL scalar and
 * the per-GA2 breakdown (spec 0106, D-10). byOperator() groups by
 * `quotes.operator_id`, the single column that partitions the branch's
 * rows (a request with no operator becomes its own "Non assegnato" group for
 * free, via SQL `GROUP BY` on a nullable column).
 *
 * partitioned() is the default path: when every counted value belongs to
 * exactly one quote ('quotes.id', 'distinct quotes.id', 'notes.id'), the
 * groups are disjoint and the TOTAL is exactly their sum, so ONE grouped
 * query yields both (D-10 rev. 2026-09-29: on 1M requests the separate total
 * doubled the dashboard's cost for an identical number). total() survives
 * for the one count that is NOT partitioned by operator — 'distinct
 * registries.id', where the same company can sit under two GA2 (AC-015).
 *
 * `$countExpression` is always a small, hard-coded literal passed by the
 * calling indicator — never user input, the same
 * `selectRaw('count(distinct quotes.id) as requests_count')` idiom already
 * used by RequestCategoryTabsResolver for the identical need (backend.md
 * §8: the SQL-injection sink is raw SQL built from INPUT, not a static
 * aggregate expression).
 */
final class QuoteCountAggregator
{
    public function partitioned(Builder $query, string $countExpression): IndicatorResult
    {
        $byOperator = $this->byOperator($query, $countExpression);

        return new IndicatorResult(array_sum(array_column($byOperator, 'value')), $byOperator);
    }

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
