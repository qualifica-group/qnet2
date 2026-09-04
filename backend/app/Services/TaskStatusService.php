<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\DataObjects\TaskStatuses\CreateTaskStatusData;
use App\DataObjects\TaskStatuses\UpdateTaskStatusData;
use App\Models\TaskStatus;
use App\Services\Statuses\StatusOrderManager;
use App\Services\Statuses\SystemStatusGuard;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Business logic for the `task-statuses` resource (spec 0101, D-4): the
 * configurator behind `tasks.task_status_id` and the SINGLE source of a
 * Task's completion percentage (D-6) — changing a row's
 * `completion_percentage` moves every Task in that status at once, with no
 * backfill (AC-021).
 *
 * `sort_order` is server-managed — placed by StatusOrderManager::placeNew()
 * on create, resequenced by reorder(); the six mandatory system rows (D-5)
 * are protected by the shared SystemStatusGuard on both update() and
 * delete().
 *
 * The controller stays thin; this Service is the single authority.
 */
class TaskStatusService
{
    /**
     * The projection every for-select query reads. `is_active` is part of
     * it because the resource EXPOSES it in `meta` (the reorder sheet marks
     * the deactivated rows it asked for via `include_inactive`): a column
     * left out here would silently serialize as null, not as false.
     *
     * Identity, the badge
     * attributes (`color`/`icon`), the system phase (D-5) and the
     * completion the Task form projects from the picked status (D-6,
     * AC-084).
     *
     * @var array<int, string>
     */
    private const array FOR_SELECT_COLUMNS = ['id', 'name', 'color', 'icon', 'is_active', 'system_key', 'completion_percentage'];

    public function __construct(
        private readonly StatusOrderManager $orderManager,
        private readonly SystemStatusGuard $systemStatusGuard,
    ) {}

    public function create(CreateTaskStatusData $data): TaskStatus
    {
        return TaskStatus::create([
            ...$data->attributes(),
            'sort_order' => $this->orderManager->placeNew(TaskStatus::class),
        ]);
    }

    public function update(TaskStatus $taskStatus, UpdateTaskStatusData $data): TaskStatus
    {
        $attributes = $data->submittedAttributes();

        // A system row accepts ONLY name/color/icon/completion_percentage
        // (AC-043), checked by KEY on what the client actually submitted,
        // before anything is written.
        $this->systemStatusGuard->assertUpdatable($taskStatus, $attributes);

        // Unconditional save: fires the model's saved event even when no
        // native attribute changed, so the HasCustomFields write pipeline
        // (spec 0021) persists a custom-fields-only edit. A clean save runs
        // no UPDATE query.
        $taskStatus->fill($attributes)->save();

        return $taskStatus->fresh();
    }

    /**
     * Restrictive delete (spec 0101, D-8b): a task status still referenced by a
     * Task cannot be removed — never a cascade, so no Task is ever removed as
     * a side effect (AC-041). Defense in depth: the FK is also
     * restrictOnDelete at the schema layer. The generic bulk-delete goes
     * through the SAME method via TaskStatusesTableDefinition::deleteModel(). The
     * system-row guard (D-8c) runs FIRST: one of the six mandatory rows is
     * never deletable, regardless of whether it happens to be unreferenced
     * (AC-042).
     */
    public function delete(TaskStatus $taskStatus): void
    {
        $this->systemStatusGuard->assertDeletable($taskStatus);

        if ($taskStatus->tasks()->exists()) {
            abort(409, 'This task status is used by a task and cannot be deleted.');
        }

        $taskStatus->delete();
    }

    /**
     * Resequences every custom row to $orderedIds' order and returns the
     * fresh, complete, ordered list (AC-047). See
     * App\Services\Statuses\StatusOrderManager::reorder() for the
     * validation/renormalization rules: the six system rows are pinned
     * (head/tail) and can neither be moved nor omitted.
     *
     * @param  array<int, int>  $orderedIds
     * @return EloquentCollection<int, TaskStatus>
     */
    public function reorder(array $orderedIds): EloquentCollection
    {
        /** @var EloquentCollection<int, TaskStatus> $reordered */
        $reordered = $this->orderManager->reorder(TaskStatus::class, $orderedIds);

        return $reordered;
    }

    /**
     * Minimal, searchable, paginated task status list for the for-select
     * standard (ADR 0011). Rows are ordered by `sort_order` first so the
     * select mirrors the table's display order.
     *
     * By default only `is_active = true` rows are eligible: a deactivated
     * row stays visible on the Tasks already assigned to it, but is never
     * (re-)selectable. `include_inactive` (T-03c) lifts that filter for the
     * reorder sheet, which MUST see every row — `ordered_ids` is validated
     * against the full set, so a list missing the deactivated rows would be
     * rejected as incomplete on every drag.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = TaskStatus::query()->select(self::FOR_SELECT_COLUMNS);

        if (! $query->includeInactive) {
            $base->where('is_active', true);
        }

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, TaskStatus> $page */
        $page = $base->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedIds($page, $query);

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search AND the
     * `is_active` filter (a Task keeps showing its current task status even
     * after it is deactivated), same projection applies. Total is unaffected.
     *
     * @param  Collection<int, TaskStatus>  $page
     * @return Collection<int, TaskStatus>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, TaskStatus> $hydrated */
        $hydrated = TaskStatus::query()
            ->select(self::FOR_SELECT_COLUMNS)
            ->whereIn('id', $missingIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
