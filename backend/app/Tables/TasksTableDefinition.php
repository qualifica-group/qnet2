<?php

declare(strict_types=1);

namespace App\Tables;

use App\Authorization\TasksAuthorization;
use App\Models\Task;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use App\Services\Tasks\TaskAbilityResolver;
use App\Services\Tasks\TaskActionAvailability;
use App\Services\Tasks\TaskStatusResolver;
use App\Services\Tasks\TaskVisibilityScope;
use App\Services\Tasks\TaskWriteLock;
use App\Services\TaskService;
use App\Tables\Tasks\TaskAdvancedFilterApplier;
use App\Tables\Tasks\TaskAdvancedFilterCatalog;
use App\Tables\Tasks\TaskAggregateColumns;
use App\Tables\Tasks\TaskCellWriter;
use App\Tables\Tasks\TaskColumnCatalog;
use App\Tables\Tasks\TaskKanbanGroupScope;
use App\Tables\Tasks\TaskRelationColumns;
use App\Tables\Tasks\TaskRowMapper;
use App\Tables\Tasks\TaskTreeScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

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
 * two AGGREGATE columns, delegated to TaskAggregateColumns; every other
 * derived column is delegated to TaskRelationColumns (file-size split,
 * engineering.md §6).
 *
 * deleteModel() routes the generic bulk-delete through TaskService::delete(),
 * so the sub-task guard cannot be side-stepped (AC-016). updateCell() (spec
 * 0156, D-8) routes the generic inline cell-edit through TaskCellWriter, so
 * it too runs every guard `TaskService::update()` already enforces on a
 * single-task PATCH.
 */
class TasksTableDefinition extends AbstractTableDefinition
{
    private const string COMPLETION_PERCENTAGE_COLUMN = 'completion_percentage';

    /**
     * Per-block cap for `tasks`, five times the shared
     * `BaseApiController::MAX_LIMIT`. Introduced by spec 0157 for the
     * one-shot Kanban load; since spec 0164 the Kanban pages each column in
     * blocks of 50, so this is only the ceiling a single request may ask for.
     */
    private const int MAX_ROWS_LIMIT = 500;

    /**
     * The seven domain-action flags of TasksAuthorization::actionPermissions()
     * exposed as row actions (spec 0156, D-5): the action key IS the flag
     * key for all seven, so a single loop maps them — never a second
     * evaluation of the matrix TaskCompletionService/TaskActionService
     * themselves re-assert.
     *
     * @var array<int, string>
     */
    private const array DOMAIN_ACTION_FLAGS = ['complete', 'uncomplete', 'approve', 'reject', 'block', 'unblock', 'request_update'];

    public function __construct(
        private readonly TaskService $service,
        private readonly TaskStatusResolver $statusResolver,
        private readonly TaskRelationColumns $relationColumns,
        private readonly TaskAdvancedFilterApplier $advancedFilterApplier,
        private readonly TaskAggregateColumns $aggregateColumns,
        private readonly TaskCellWriter $cellWriter,
        private readonly TasksAuthorization $authorization,
        private readonly TaskRowMapper $rowMapper,
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
     * Allowed action keys for a single row, via TaskPolicy — which carries
     * the D-9 visibility scoping, answered in memory here because both
     * membership relations are eager-loaded in baseQuery(). Spec 0156, D-5
     * adds the seven domain actions (TasksAuthorization::actionPermissions(),
     * the SAME matrix the detail's own buttons read) plus `duplicate`/
     * `notes`, both riding on the `view`/`create` gates already resolved
     * above rather than a second Policy call.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var Task $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if ($this->authorizeDelete($actor, $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        $canView = in_array('view', $allowed, true);

        if ($canView && $actor->can('tasks.create')) {
            $allowed[] = 'duplicate';
        }

        if ($canView) {
            $allowed[] = 'notes';
        }

        $permissions = $this->authorization->actionPermissions($actor, $row);

        foreach (self::DOMAIN_ACTION_FLAGS as $flag) {
            if ($permissions[$flag] ?? false) {
                $allowed[] = $flag;
            }
        }

        return $allowed;
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

    /**
     * Spec 0125, D-2: the Gate alone lets a super-admin assignee through, so
     * the delete row of the matrix is ANDed here too — the grid row-action
     * and the bulk-delete `forbidden` verdict then match the Service's 403.
     */
    public function authorizeDelete(User $actor, Model $row): bool
    {
        /** @var Task $row */
        return Gate::forUser($actor)->allows('delete', $row) && TaskAbilityResolver::canDelete($actor, $row);
    }

    /**
     * Spec 0156, contract: `editable` of a row = update allowed AND the Task
     * is not closed/blocked for writing, a super-admin excluded from that
     * second half — the same coarse row-level UI hint `editable` already is
     * everywhere else in this engine (D-2 of spec 0053: "il config è un
     * suggerimento, la catena di guardie del PATCH è la verità"), so this
     * deliberately reads only $row's OWN `is_blocked`/status phase
     * (TaskWriteLock::isLocked()), never the ancestor-chain cascade
     * (TaskWriteLock::isLockedByAncestor()) — the per-field write TaskCellWriter
     * -> TaskService::update() runs is the one place that walk is judged for
     * real, and it stays a per-row query the grid's page cannot afford to
     * repeat for every locked-by-ancestor sub-task.
     */
    public function authorizeUpdate(User $actor, Model $row): bool
    {
        /** @var Task $row */
        if (! Gate::forUser($actor)->allows('update', $row)) {
            return false;
        }

        if ($actor->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE)) {
            return true;
        }

        return ! TaskWriteLock::isLocked($row);
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
        return self::MAX_ROWS_LIMIT;
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
        if ($columnId === self::COMPLETION_PERCENTAGE_COLUMN) {
            $this->statusResolver->applyFilter($query, $columnConfig, $filter);

            return true;
        }

        if ($this->aggregateColumns->applyFilter($query, $columnId, $filter)) {
            return true;
        }

        return $this->relationColumns->applyFilter($query, $columnId, $filter);
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId === self::COMPLETION_PERCENTAGE_COLUMN) {
            $this->statusResolver->applySort($query, $direction);

            return true;
        }

        if ($this->aggregateColumns->applySort($query, $columnId, $direction)) {
            return true;
        }

        return $this->relationColumns->applySort($query, $columnId, $direction);
    }

    /**
     * Spec 0156, D-1: the quick search also matches an exact numeric id,
     * OR-combined with the `title` LIKE the generic engine already applies
     * to the other searchable column. `$pattern` arrives already
     * `%…%`-wrapped and LIKE-escaped (TableQueryBuilder::applySearch()); the
     * raw term is safely recovered by stripping the two wrapping `%` — safe
     * because a purely-numeric term carries no character `escapeLike()`
     * would ever have touched.
     *
     * @param  Builder<Task>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        if ($columnId !== 'id') {
            return false;
        }

        $term = substr($pattern, 1, -1);

        if ($term === '' || ! ctype_digit($term)) {
            return true; // handled: no numeric id to match, adds no clause.
        }

        $query->orWhere('tasks.id', '=', (int) $term);

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
