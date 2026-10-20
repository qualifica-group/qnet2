<?php

declare(strict_types=1);

namespace App\Tables\TaskTemplates;

use App\Tables\Concerns\UnwrapsMultiFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The `items_count` AGGREGATE column on `task-templates` (no real DB column
 * — resolved via baseQuery()'s `withCount('items')`, spec 0124, AC-008):
 * WHERE cannot see a SELECT-list alias (MySQL), while ORDER BY IS handled
 * generically by the engine. Filter + distinct-values are delegated here
 * instead, via a relation-count condition (`has()`), mirroring
 * ProductCategoryCountColumn. The column exposes its value list, so the grid
 * sends the `multi` widget (Set of exact counts + number condition).
 */
final class TaskTemplateItemsCountColumn
{
    use UnwrapsMultiFilter;

    private const int MAX_FILTER_VALUES = 100;

    /**
     * Excel-like distinct values (spec 0004/0005): `$query` already carries
     * the `items_count` alias (baseQuery() applies withCount('items')) and
     * every cross-column filter. Wrapping it as a derived table lets us
     * DISTINCT on that alias without re-aggregating or touching a real
     * column that doesn't exist. Search narrows on the count's string
     * representation, bound + LIKE-escaped.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    public function distinctValues(Builder $query, ?string $search, int $limit): array
    {
        $counts = DB::query()->fromSub($query, 'task_templates_with_counts')->select('items_count')->distinct();

        if ($search !== null && $search !== '') {
            $counts->where('items_count', 'like', '%'.$this->escapeLike($search).'%');
        }

        return $counts
            ->orderBy('items_count')
            ->limit($limit)
            ->pluck('items_count')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, array $filter): bool
    {
        $this->dispatchDerivedFilter(
            $filter,
            fn (array $set): mixed => $this->applySet($query, $set),
            fn (array $condition): mixed => $this->applyCondition($query, $condition),
        );

        return true;
    }

    /**
     * SET sub-model: the ticked values are exact counts, matched as an OR of
     * count comparisons on `items`.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    private function applySet(Builder $query, array $filter): void
    {
        $values = is_array($filter['values'] ?? null) ? $filter['values'] : [];
        $counts = array_slice(array_values(array_filter($values, 'is_numeric')), 0, self::MAX_FILTER_VALUES);

        if ($counts === []) {
            return;
        }

        $query->where(function (Builder $match) use ($counts): void {
            foreach ($counts as $count) {
                $match->orHas('items', '=', max(0, (int) $count));
            }
        });
    }

    /**
     * A `number` condition (equals/notEqual/greaterThan(OrEqual)/
     * lessThan(OrEqual)/inRange), applied as a count comparison on the
     * `items` relation via has() — a bound correlated-subquery WHERE that is
     * portable across drivers and respected by the (clone)->count() total.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    private function applyCondition(Builder $query, array $filter): void
    {
        $type = is_string($filter['type'] ?? null) ? $filter['type'] : 'equals';

        if ($type === 'inRange') {
            $from = $this->intOrNull($filter['filter'] ?? null);
            $to = $this->intOrNull($filter['filterTo'] ?? null);

            if ($from !== null) {
                $query->has('items', '>=', $from);
            }

            if ($to !== null) {
                $query->has('items', '<=', $to);
            }

            return;
        }

        $value = $this->intOrNull($filter['filter'] ?? null);

        if ($value === null) {
            return; // blank / notBlank / malformed → no constraint
        }

        $operator = match ($type) {
            'notEqual' => '!=',
            'lessThan' => '<',
            'lessThanOrEqual' => '<=',
            'greaterThan' => '>',
            'greaterThanOrEqual' => '>=',
            default => '=', // 'equals'
        };

        $query->has('items', $operator, $value);
    }

    /**
     * Coerce a filter payload value to a non-negative int, or null when it
     * is not a usable numeric value (so the filter adds no constraint).
     */
    private function intOrNull(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max(0, (int) $value);
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
