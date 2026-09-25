<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskActionAvailability;
use App\Services\Tasks\TaskStatusResolver;
use App\Services\Tasks\TaskVisibilityScope;
use App\Services\TaskService;
use App\Tables\Tasks\TaskAdvancedFilterApplier;
use App\Tables\Tasks\TaskAdvancedFilterCatalog;
use App\Tables\Tasks\TaskAggregateColumns;
use App\Tables\Tasks\TaskCellWriter;
use App\Tables\Tasks\TaskColumnCatalog;
use App\Tables\Tasks\TaskDerivedColumnResolver;
use App\Tables\Tasks\TaskIdSearchMatcher;
use App\Tables\Tasks\TaskKanbanGroupScope;
use App\Tables\Tasks\TaskRelationColumns;
use App\Tables\Tasks\TaskRowActionResolver;
use App\Tables\Tasks\TaskRowMapper;
use App\Tables\Tasks\TaskTableConstants;
use App\Tables\Tasks\TaskTreeScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Table definition for the `tasks` domain (spec 0101, extended by spec 0156
 * for q-net alignment).
 *
 * baseQuery() is scoped by TaskVisibilityScope (D-9): without
 * `tasks.viewAll` the actor lists only the Task they created, requested, are
 * assigned to or watch. Rows, exports and distinct filter values all derive
 * from this ONE query, so they are scoped by construction — including the
 * export, which ExportService already runs with `Auth::setUser($actor)`
 * (AC-065), and including the null-actor case, which the scope answers
 * fail-closed (AC-064).
 *
 * `completion_percentage` is the one column with no `tasks` column behind it
 * (D-6): its value, its ORDER BY and its WHERE all come from
 * TaskStatusResolver, so the badge and the grid can never disagree
 * (AC-020/AC-022). `actual_minutes`/`parent_title` (spec 0156, D-2) are the
 * two AGGREGATE columns; the whole filter/sort dispatch across it,
 * TaskStatusResolver and every other derived column is delegated to
 * TaskDerivedColumnResolver (file-size split, engineering.md §6).
 *
 * deleteModel() routes the generic bulk-delete through TaskService::delete(),
 * so the sub-task guard cannot be side-stepped (AC-016). updateCell() (spec
 * 0156, D-8) routes the generic inline cell-edit through TaskCellWriter, so
 * it too runs every guard `TaskService::update()` already enforces on a
 * single-task PATCH. actionsFor()/authorizeDelete()/authorizeUpdate() are
 * delegated to TaskRowActionResolver, same reason.
 */
class TasksTableDefinition extends AbstractTableDefinition
{
    public function __construct(
        private readonly TaskService $service,
        private readonly TaskRelationColumns $relationColumns,
        private readonly TaskAdvancedFilterApplier $advancedFilterApplier,
        private readonly TaskAggregateColumns $aggregateColumns,
        private readonly TaskCellWriter $cellWriter,
        private readonly TaskRowActionResolver $rowActionResolver,
        private readonly TaskRowMapper $rowMapper,
        private readonly TaskDerivedColumnResolver $derivedColumnResolver,
    ) {}

    public function domain(): string
    {
        return 'tasks';
    }

    /**
     * @return class-string<Task>
     */
    public function modelClass(): string
    {
        return Task::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives TaskPolicy::viewAny from
    // modelClass() (tasks.viewAny, AC-052).

    /**
     * @return Builder<Task>
     */
    public function baseQuery(): Builder
    {
        // The five configurators are eager-loaded for their badge attributes
        // (colour/icon/label, AC-072) and `taskStatus` doubles as the source
        // of the derived percentage (TaskStatusResolver::EAGER_LOADS).
        // `assignees`/`watchers` carry the two to-many cells AND let
        // TaskVisibilityScope::isVisibleTo() answer actionsFor()'s per-row
        // Gate calls in memory instead of querying; the assignees' Sedi do
        // the same for the `viewSite` tier (spec 0148). `withCount('subtasks')`
        // resolves the `has_subtasks` cell in the SAME query rather than one
        // EXISTS per row, and deliberately counts children the actor may not
        // see — the same unscoped fact the delete guard asserts on (D-8a).
        // `withCount('notes')` (spec 0156, D-5) backs the `notes` action's
        // badge; `TaskActionAvailability::withOpenSubtasksCount()` (spec
        // 0153) preloads the count the `complete`/`approve` row actions'
        // guard needs, so actionsFor() never N+1s across the page.
        // `addSelect($this->aggregateColumns->selects())` (spec 0156, D-2)
        // resolves `actual_minutes`/`parent_title` in the SAME query too.
        $query = Task::query()
            ->withCount(['subtasks', 'notes'])
            ->with([
                ...TaskStatusResolver::EAGER_LOADS,
                'taskType',
                'taskPriority',
                'taskImportance',
                'taskCategory',
                'registry',
                'opportunity',
                'workOrder',
                'workOrderStage',
                'requester',
                'creator',
                'assignees.employment.operationalSites',
                'watchers',
            ])
            ->addSelect($this->aggregateColumns->selects());

        return TaskVisibilityScope::scopeToActor(
            TaskActionAvailability::withOpenSubtasksCount($query),
            Auth::user(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return TaskColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return TaskColumnCatalog::filters();
    }

    /**
     * The work-order Task board's filters (spec 0147), extended by spec
     * 0156 (`registry`/`work_order`, `due`'s `this_month`).
     *
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        $actor = Auth::user();

        return TaskAdvancedFilterCatalog::advancedFilters($actor instanceof User ? $actor : null);
    }

    /**
     * `status`/`due`/`assignment` are derived (TaskAdvancedFilterApplier); the
     * relation filters go through the generic whereHas-by-id.
     *
     * @param  Builder<Task>  $query
     * @param  array<string, mixed>  $descriptor
     */
    public function applyAdvancedFilter(Builder $query, string $name, array $descriptor, mixed $value): bool
    {
        /** @var User|null $actor */
        $actor = Auth::user();

        return $this->advancedFilterApplier->apply($query, $name, $value, $actor)
            || parent::applyAdvancedFilter($query, $name, $descriptor, $value);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return TaskColumnCatalog::actions();
    }

    /**
     * Spec 0153, D-2: most-recently-updated first, `id desc` breaking ties
     * (two rows touched in the same second).
     *
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'updated_at', 'direction' => 'desc'],
            ['columnId' => 'id', 'direction' => 'desc'],
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
     * Map a Task to the row payload (App\Tables\Tasks\TaskRowMapper). `actions`
     * is attached by the generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Task $row */
        return $this->rowMapper->map($row);
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var Task $row */
        return $this->rowActionResolver->actionsFor($actor, $row);
    }

    /**
     * Delegate to TaskService::delete() so the generic bulk-delete endpoint
     * respects the SAME sub-task guard as DELETE /tasks/{task} (D-8a,
     * AC-016).
     */
    public function deleteModel(Model $model): void
    {
        /** @var Task $model */
        /** @var User $actor */
        $actor = Auth::user();
        $this->service->delete($model, $actor);
    }

    public function authorizeDelete(User $actor, Model $row): bool
    {
        /** @var Task $row */
        return $this->rowActionResolver->authorizeDelete($actor, $row);
    }

    public function authorizeUpdate(User $actor, Model $row): bool
    {
        /** @var Task $row */
        return $this->rowActionResolver->authorizeUpdate($actor, $row);
    }

    /**
     * Spec 0156, D-8: routes the inline cell edit through TaskCellWriter ->
     * TaskService::update(), never a raw `$row->update()` — every structural
     * guard a single-task PATCH already enforces (TaskWriteLock,
     * TaskManualStatusGuard, the "Fase" guard, watcher overlap, the 0153
     * D-13 notification map) runs here too.
     */
    public function updateCell(Model $row, string $columnId, mixed $value): Model
    {
        /** @var Task $row */
        /** @var User $actor */
        $actor = Auth::user();

        return $this->cellWriter->write($row, $columnId, $value, $actor);
    }

    /**
     * Spec 0156, D-3: the sum of `estimated_minutes` over the WHOLE filtered
     * set (never the page) — the footer total the frontend renders.
     *
     * @param  Builder<Task>  $query
     * @return array{estimated_minutes_total: int}
     */
    public function aggregates(Builder $query): array
    {
        return ['estimated_minutes_total' => (int) $query->sum('tasks.estimated_minutes')];
    }

    /**
     * Spec 0157, D-1: `tasks` is the one domain with tree/hierarchical row
     * scoping — Sintetica shows only root Task at the top level, expanding
     * a node to fetch its direct children, both under the SAME filters,
     * search and sort as Analitica.
     */
    public function supportsTree(): bool
    {
        return true;
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function applyTreeScope(Builder $query, ?int $parentId): void
    {
        TaskTreeScope::apply($query, $parentId);
    }

    public function maxRowsLimit(): int
    {
        return TaskTableConstants::MAX_ROWS_LIMIT;
    }

    /**
     * Spec 0164, D-2: `tasks` is the one domain with server-side Kanban
     * column grouping — the "per stato"/"per scadenza" boards load their
     * columns via `kanbanGroup` instead of classifying rows client-side.
     */
    public function supportsKanbanGroups(): bool
    {
        return true;
    }

    /**
     * @param  Builder<Task>  $query
     * @param  array{by: string, key: int|string}  $kanbanGroup
     */
    public function applyKanbanGroupScope(Builder $query, array $kanbanGroup): void
    {
        TaskKanbanGroupScope::apply($query, $kanbanGroup);
    }

    /**
     * @param  Builder<Task>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return $this->derivedColumnResolver->applyFilter($query, $columnId, $columnConfig, $filter);
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        return $this->derivedColumnResolver->applySort($query, $columnId, $direction);
    }

    /**
     * Spec 0156, D-1: the quick search also matches an exact numeric id,
     * OR-combined with the `title` LIKE the generic engine already applies
     * to the other searchable column (App\Tables\Tasks\TaskIdSearchMatcher).
     *
     * @param  Builder<Task>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        if ($columnId !== 'id') {
            return false;
        }

        TaskIdSearchMatcher::apply($query, $pattern);

        return true;
    }

    /**
     * @param  Builder<Task>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return $this->relationColumns->distinctValues($columnId, $search, $query, $limit);
    }
}
