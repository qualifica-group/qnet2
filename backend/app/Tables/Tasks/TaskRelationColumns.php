<?php

declare(strict_types=1);

namespace App\Tables\Tasks;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The relation- and hierarchy-derived column machinery for the `tasks`
 * domain (spec 0101), extracted out of TasksTableDefinition (file-size
 * split, engineering.md §6). Mirrors QuoteRelationColumns/
 * ContractRelationColumns.
 *
 * Three shapes live here:
 *  - SIMPLE_RELATIONS: the ten own-FK, name-labelled `set` columns (the five
 *    configurators plus registry/opportunity/work order/richiedente/
 *    creatore) — a `whereHas` set filter, a correlated-subquery sort and
 *    Excel-like distinct values (spec 0004/0005);
 *  - PIVOT_RELATIONS: `assignees`/`watchers`, to-many over `task_assignee`/
 *    `task_watcher` — a `whereHas` set filter and distinct values through a
 *    join, never a sort (no single related row to order by);
 *  - HIERARCHY predicates: `has_subtasks`/`is_subtask` (D-12), plain
 *    boolean conditions on `parent_task_id` / the `subtasks` relation. Both
 *    deliberately IGNORE the visibility scope, exactly like the delete guard
 *    (D-8a): whether a Task has children is a fact about the data, not about
 *    who is looking.
 *
 * Every column id reaching this class comes from the definition's own static
 * catalogue (TaskColumnCatalog) — never client input — so every relation,
 * table and column name below is an allow-list value, never interpolated
 * from a request (AC-073, backend.md §8): no raw SQL fragment is built here,
 * and every filter value stays a bound parameter.
 */
final class TaskRelationColumns
{
    /**
     * Maximum number of names honoured in a derived-column set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /** Every related table projected here calls its display name `name`. */
    private const string DEFAULT_LABEL_COLUMN = 'name';

    private const string TASKS_TABLE = 'tasks';

    private const string USERS_TABLE = 'users';

    private const string PARENT_FK = 'parent_task_id';

    private const string SUBTASKS_RELATION = 'subtasks';

    private const string HAS_SUBTASKS_COLUMN = 'has_subtasks';

    private const string IS_SUBTASK_COLUMN = 'is_subtask';

    /**
     * Own-FK relation columns: relation accessor, related table, owning FK
     * and — only where it is not `name` — the related row's label column.
     *
     * `work_order` is the one entry with a different label column: a Commessa
     * displays as its `title` (its `code` is a separate attribute).
     *
     * @var array<string, array{relation: string, table: string, fk: string, label?: string}>
     */
    private const array SIMPLE_RELATIONS = [
        'registry' => ['relation' => 'registry', 'table' => 'registries', 'fk' => 'registry_id'],
        'task_type' => ['relation' => 'taskType', 'table' => 'task_types', 'fk' => 'task_type_id'],
        'task_status' => ['relation' => 'taskStatus', 'table' => 'task_statuses', 'fk' => 'task_status_id'],
        'task_priority' => ['relation' => 'taskPriority', 'table' => 'task_priorities', 'fk' => 'task_priority_id'],
        'task_importance' => ['relation' => 'taskImportance', 'table' => 'task_importances', 'fk' => 'task_importance_id'],
        'task_category' => ['relation' => 'taskCategory', 'table' => 'task_categories', 'fk' => 'task_category_id'],
        'requester' => ['relation' => 'requester', 'table' => self::USERS_TABLE, 'fk' => 'requester_id'],
        'creator' => ['relation' => 'creator', 'table' => self::USERS_TABLE, 'fk' => 'creator_id'],
        'opportunity' => ['relation' => 'opportunity', 'table' => 'opportunities', 'fk' => 'opportunity_id'],
        'work_order' => ['relation' => 'workOrder', 'table' => 'work_orders', 'fk' => 'work_order_id', 'label' => 'title'],
    ];

    /**
     * The two user pivots (D-1): relation accessor -> pivot table.
     *
     * @var array<string, array{relation: string, pivot: string}>
     */
    private const array PIVOT_RELATIONS = [
        'assignees' => ['relation' => 'assignees', 'pivot' => 'task_assignee'],
        'watchers' => ['relation' => 'watchers', 'pivot' => 'task_watcher'],
    ];

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $filter): bool
    {
        if ($columnId === self::HAS_SUBTASKS_COLUMN || $columnId === self::IS_SUBTASK_COLUMN) {
            $this->applyHierarchyFilter($query, $columnId, $filter);

            return true;
        }

        $pivot = self::PIVOT_RELATIONS[$columnId] ?? null;

        if ($pivot !== null) {
            $this->applyNameWhereHas($query, $pivot['relation'], $this->filterValues($filter));

            return true;
        }

        $config = self::SIMPLE_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        $this->applyNameWhereHas($query, $config['relation'], $this->filterValues($filter), $this->labelColumn($config));

        return true;
    }

    /**
     * ORDER BY the related row's label via a correlated subquery — never a
     * row-multiplying JOIN. The pivots and the hierarchy booleans never reach
     * here: the catalogue declares them `sortable: false`.
     *
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $columnId, string $direction): bool
    {
        $config = self::SIMPLE_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        $label = $this->labelColumn($config);

        $subquery = DB::table($config['table'])
            ->select($label)
            ->whereColumn($config['table'].'.id', self::TASKS_TABLE.'.'.$config['fk'])
            ->limit(1);

        $query->orderBy($subquery, $direction);

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005): the related row's label
     * among the rows matching $query — i.e. scoped by every OTHER active
     * filter AND by the visibility scope already applied to
     * TasksTableDefinition::baseQuery(). `has_subtasks`/`is_subtask` and
     * `completion_percentage` declare `hasFilterValues: false`, so this is
     * never reached for them.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>|null
     */
    public function distinctValues(string $columnId, ?string $search, Builder $query, int $limit): ?array
    {
        $pivot = self::PIVOT_RELATIONS[$columnId] ?? null;

        if ($pivot !== null) {
            return $this->distinctPivotNames($pivot['pivot'], $search, $query, $limit);
        }

        $config = self::SIMPLE_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return null;
        }

        $relatedIds = (clone $query)->whereNotNull(self::TASKS_TABLE.'.'.$config['fk'])->select(self::TASKS_TABLE.'.'.$config['fk']);
        $label = $this->labelColumn($config);

        return DB::table($config['table'])
            ->whereIn('id', $relatedIds)
            ->when($search !== null && $search !== '', function ($builder) use ($label, $search): void {
                $builder->where($label, 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy($label)
            ->limit($limit)
            ->pluck($label)
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * Distinct user names among the tasks matching $query, joined through one
     * of the two user pivots — mirrors QuoteRelationColumns::
     * distinctManagerNames() over this module's own pivot tables.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    private function distinctPivotNames(string $pivotTable, ?string $search, Builder $query, int $limit): array
    {
        $taskIds = (clone $query)->select(self::TASKS_TABLE.'.id');

        return DB::table(self::USERS_TABLE)
            ->join($pivotTable, $pivotTable.'.user_id', '=', self::USERS_TABLE.'.id')
            ->whereIn($pivotTable.'.task_id', $taskIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where(self::USERS_TABLE.'.'.self::DEFAULT_LABEL_COLUMN, 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy(self::USERS_TABLE.'.'.self::DEFAULT_LABEL_COLUMN)
            ->limit($limit)
            ->pluck(self::USERS_TABLE.'.'.self::DEFAULT_LABEL_COLUMN)
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * `whereHas` on a relation's own label column, with BOUND values — never
     * a raw fragment built from client input. An empty value set is a no-op,
     * never an empty `IN ()`.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $values
     */
    private function applyNameWhereHas(Builder $query, string $relation, array $values, string $label = self::DEFAULT_LABEL_COLUMN): void
    {
        if ($values === []) {
            return;
        }

        $query->whereHas($relation, static function (Builder $relatedQuery) use ($label, $values): void {
            $relatedQuery->whereIn($label, $values);
        });
    }

    /**
     * The two hierarchy predicates (D-12). Both requested together, or
     * neither, is a no-op: every row is one or the other.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    private function applyHierarchyFilter(Builder $query, string $columnId, array $filter): void
    {
        $values = $this->booleanFilterValues($filter);

        if (count($values) !== 1) {
            return;
        }

        $wanted = $values[0];

        if ($columnId === self::IS_SUBTASK_COLUMN) {
            $wanted
                ? $query->whereNotNull(self::TASKS_TABLE.'.'.self::PARENT_FK)
                : $query->whereNull(self::TASKS_TABLE.'.'.self::PARENT_FK);

            return;
        }

        $wanted
            ? $query->whereHas(self::SUBTASKS_RELATION)
            : $query->whereDoesntHave(self::SUBTASKS_RELATION);
    }

    /**
     * The distinct booleans carried by a boolean filter payload, in the two
     * shapes the generic engine accepts (a `values` set, or a single
     * `filter`/`type` scalar) — the same parsing FilterApplier does for a
     * REAL boolean column, which cannot be reused here because these two
     * columns have no column to compare against.
     *
     * @param  array<string, mixed>  $filter
     * @return array<int, bool>
     */
    private function booleanFilterValues(array $filter): array
    {
        $values = $filter['values'] ?? null;

        if (is_array($values)) {
            $clean = [];

            foreach ($values as $value) {
                $parsed = $this->parseBoolean($value);

                if ($parsed !== null && ! in_array($parsed, $clean, true)) {
                    $clean[] = $parsed;
                }
            }

            return $clean;
        }

        $single = $this->parseBoolean($filter['filter'] ?? ($filter['type'] ?? null));

        return $single === null ? [] : [$single];
    }

    private function parseBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return match ($value) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    /**
     * The related row's label column. Comes from the static map only — never
     * from request input, so it is safe as a column identifier.
     *
     * @param  array{relation: string, table: string, fk: string, label?: string}  $config
     */
    private function labelColumn(array $config): string
    {
        return $config['label'] ?? self::DEFAULT_LABEL_COLUMN;
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

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
