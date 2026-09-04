<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskStatusResolver;
use App\Services\Tasks\TaskVisibilityScope;
use App\Services\TaskService;
use App\Tables\Tasks\TaskColumnCatalog;
use App\Tables\Tasks\TaskRelationColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `tasks` domain (spec 0101).
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
 * (AC-020/AC-022). Every other derived column is delegated to
 * TaskRelationColumns (file-size split, engineering.md §6).
 *
 * deleteModel() routes the generic bulk-delete through TaskService::delete(),
 * so the sub-task guard cannot be side-stepped (AC-016).
 */
class TasksTableDefinition extends AbstractTableDefinition
{
    private const string COMPLETION_PERCENTAGE_COLUMN = 'completion_percentage';

    public function __construct(
        private readonly TaskService $service,
        private readonly TaskStatusResolver $statusResolver,
        private readonly TaskRelationColumns $relationColumns,
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
        // Gate calls in memory instead of querying. `withCount('subtasks')`
        // resolves the `has_subtasks` cell in the SAME query rather than one
        // EXISTS per row, and deliberately counts children the actor may not
        // see — the same unscoped fact the delete guard asserts on (D-8a).
        return TaskVisibilityScope::scopeToActor(
            Task::query()->withCount('subtasks')->with([
                'taskStatus',
                'taskType',
                'taskPriority',
                'taskImportance',
                'taskCategory',
                'registry',
                'opportunity',
                'workOrder',
                'requester',
                'creator',
                'assignees',
                'watchers',
            ]),
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
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return TaskColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
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
     * Map a Task to the row payload. `actions` is attached by the generic
     * TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Task $row */
        return [
            'id' => $row->id,
            'title' => $row->title,
            'registry' => $this->nameRef($row->registry),
            'task_type' => $this->badgeRef($row->taskType),
            'task_status' => $this->badgeRef($row->taskStatus),
            'task_priority' => $this->badgeRef($row->taskPriority),
            'task_importance' => $this->badgeRef($row->taskImportance),
            'task_category' => $this->badgeRef($row->taskCategory),
            'start_date' => $row->start_date,
            'end_date' => $row->end_date,
            'completion_date' => $row->completion_date,
            'requester' => $this->nameRef($row->requester),
            'creator' => $this->nameRef($row->creator),
            'assignees' => $this->summarizeUsers($row->assignees->all()),
            'watchers' => $this->summarizeUsers($row->watchers->all()),
            'completion_percentage' => $this->statusResolver->completionPercentage($row),
            'estimated_minutes' => $row->estimated_minutes,
            'is_blocked' => $row->is_blocked,
            'opportunity' => $this->nameRef($row->opportunity),
            'work_order' => $row->workOrder === null
                ? null
                : ['id' => $row->workOrder->id, 'name' => $row->workOrder->title],
            'has_subtasks' => (int) $row->subtasks_count > 0,
            'is_subtask' => $row->parent_task_id !== null,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function nameRef(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * A configurator row with its badge attributes, so the grid renders the
     * CONFIGURED colour/icon and changing them needs no code change
     * (AC-072).
     *
     * @return array{id: int, name: string, color: string|null, icon: string|null}|null
     */
    private function badgeRef(?Model $related): ?array
    {
        if ($related === null) {
            return null;
        }

        return [
            'id' => $related->id,
            'name' => $related->name,
            'color' => $related->color,
            'icon' => $related->icon,
        ];
    }

    /**
     * @param  array<int, User>  $users
     * @return array<int, array{id: int, name: string}>
     */
    private function summarizeUsers(array $users): array
    {
        return array_map(
            static fn (User $user): array => ['id' => $user->id, 'name' => $user->name],
            $users,
        );
    }

    /**
     * Allowed action keys for a single row, via TaskPolicy — which carries
     * the D-9 visibility scoping, answered in memory here because both
     * membership relations are eager-loaded in baseQuery().
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

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
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
        $this->service->delete($model);
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

        return $this->relationColumns->applySort($query, $columnId, $direction);
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
