<?php

declare(strict_types=1);

namespace App\Tables\Tasks;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * `actual_minutes` and `parent_title` (spec 0156, D-2): the two new `tasks`
 * columns with no plain column behind them. Both resolve through a
 * CORRELATED SUBQUERY, never a row-multiplying JOIN and never a raw SQL
 * fragment built from client input (AC-073, backend.md §8): every subquery
 * below is built from the SAME two fixed, allow-listed shapes
 * TaskRelationColumns/TaskStatusResolver already use elsewhere in this
 * domain (`DB::table(...)->select(...)->whereColumn(...)`) — only the
 * filter's operator/value ever varies, and those stay bound parameters.
 *
 * `selects()` feeds TasksTableDefinition::baseQuery() the SAME two subqueries
 * used for filtering/sorting, aliased as the column ids, so mapRow() reads
 * `$row->actual_minutes`/`$row->parent_title` without a second query per row.
 */
final class TaskAggregateColumns
{
    public const string ACTUAL_MINUTES = 'actual_minutes';

    public const string PARENT_TITLE = 'parent_title';

    /**
     * @return array<string, QueryBuilder>
     */
    public function selects(): array
    {
        return [
            self::ACTUAL_MINUTES => $this->actualMinutesSubquery(),
            self::PARENT_TITLE => $this->parentTitleSubquery(),
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $filter): bool
    {
        return match ($columnId) {
            self::ACTUAL_MINUTES => $this->applyNumber($query, $this->actualMinutesSubquery(), $filter),
            self::PARENT_TITLE => $this->applyText($query, $this->parentTitleSubquery(), $filter),
            default => false,
        };
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $columnId, string $direction): bool
    {
        return match ($columnId) {
            self::ACTUAL_MINUTES => $this->sort($query, $this->actualMinutesSubquery(), $direction),
            self::PARENT_TITLE => $this->sort($query, $this->parentTitleSubquery(), $direction),
            default => false,
        };
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function sort(Builder $query, QueryBuilder $subquery, string $direction): bool
    {
        $query->orderBy($subquery, $direction);

        return true;
    }

    /** The sum of every segnatempo minute logged on the Task, zero when none. */
    private function actualMinutesSubquery(): QueryBuilder
    {
        return DB::table('time_entries')
            ->select(DB::raw('coalesce(sum(minutes), 0)'))
            ->whereColumn('time_entries.task_id', 'tasks.id');
    }

    /** The parent Task's own title, null for a root Task. */
    private function parentTitleSubquery(): QueryBuilder
    {
        return DB::table('tasks as parent_tasks')
            ->select('title')
            ->whereColumn('parent_tasks.id', 'tasks.parent_task_id')
            ->limit(1);
    }

    /**
     * Mirrors App\Services\Table\FilterApplier::applyNumber() — a private,
     * standalone copy rather than a shared call because that class is typed
     * to a real string DB column, never a subquery expression (its own
     * docblock names this an explicit invariant).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    private function applyNumber(Builder $query, QueryBuilder $column, array $filter): bool
    {
        $type = is_string($filter['type'] ?? null) ? $filter['type'] : 'equals';

        if ($type === 'inRange') {
            $from = $this->numericOrNull($filter['filter'] ?? null);
            $to = $this->numericOrNull($filter['filterTo'] ?? null);

            if ($from !== null && $to !== null) {
                $query->whereBetween($column, [$from, $to]);
            }

            return true;
        }

        $value = $this->numericOrNull($filter['filter'] ?? null);

        if ($value === null) {
            return true;
        }

        $operator = match ($type) {
            'notEqual' => '!=',
            'lessThan' => '<',
            'lessThanOrEqual' => '<=',
            'greaterThan' => '>',
            'greaterThanOrEqual' => '>=',
            default => '=',
        };

        $query->where($column, $operator, $value);

        return true;
    }

    private function numericOrNull(mixed $value): int|float|null
    {
        return is_numeric($value) ? $value + 0 : null;
    }

    /**
     * Mirrors App\Services\Table\FilterApplier::applyText(), same reason as
     * applyNumber() above.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    private function applyText(Builder $query, QueryBuilder $column, array $filter): bool
    {
        $value = $filter['filter'] ?? null;

        if (! is_scalar($value) || $value === '') {
            return true;
        }

        $value = (string) $value;
        $type = is_string($filter['type'] ?? null) ? $filter['type'] : 'contains';

        match ($type) {
            'equals' => $query->where($column, '=', $value),
            'notEqual' => $query->where($column, '!=', $value),
            'startsWith' => $query->where($column, 'like', $this->escapeLike($value).'%'),
            'endsWith' => $query->where($column, 'like', '%'.$this->escapeLike($value)),
            'notContains' => $query->where($column, 'not like', '%'.$this->escapeLike($value).'%'),
            default => $query->where($column, 'like', '%'.$this->escapeLike($value).'%'),
        };

        return true;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
