<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\DataObjects\TaskCategories\CreateTaskCategoryData;
use App\DataObjects\TaskCategories\UpdateTaskCategoryData;
use App\Models\TaskCategory;
use App\Services\Lookups\LookupOrderManager;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Business logic for the `task-categories` resource (spec 0101, D-4): one of the four
 * PURE lookups behind a Task's classification — no `system_key`, no numeric
 * weight, no protected row. Every row is renameable and deletable, guarded
 * only by "in use by a Task" (D-8b).
 *
 * The controller stays thin; this Service is the single authority.
 */
class TaskCategoryService
{
    /**
     * The projection every for-select query reads. `is_active` is part of
     * it because the resource EXPOSES it in `meta` (the reorder sheet marks
     * the deactivated rows it asked for via `include_inactive`): a column
     * left out here would silently serialize as null, not as false.
     *
     * Identity plus the badge
     * attributes the select renders (`color`/`icon`).
     *
     * @var array<int, string>
     */
    private const array FOR_SELECT_COLUMNS = ['id', 'name', 'color', 'icon', 'is_active'];

    public function __construct(private readonly LookupOrderManager $orderManager) {}

    public function create(CreateTaskCategoryData $data): TaskCategory
    {
        return TaskCategory::create([
            ...$data->attributes(),
            'sort_order' => $this->orderManager->placeNew(TaskCategory::class),
        ]);
    }

    public function update(TaskCategory $taskCategory, UpdateTaskCategoryData $data): TaskCategory
    {
        $attributes = $data->submittedAttributes();

        // Unconditional save: fires the model's saved event even when no
        // native attribute changed, so the HasCustomFields write pipeline
        // (spec 0021) persists a custom-fields-only edit. A clean save runs
        // no UPDATE query.
        $taskCategory->fill($attributes)->save();

        return $taskCategory->fresh();
    }

    /**
     * Restrictive delete (spec 0101, D-8b): a task category still referenced by a
     * Task cannot be removed — never a cascade, so no Task is ever removed as
     * a side effect (AC-041). Defense in depth: the FK is also
     * restrictOnDelete at the schema layer. The generic bulk-delete goes
     * through the SAME method via TaskCategoriesTableDefinition::deleteModel().
     */
    public function delete(TaskCategory $taskCategory): void
    {
        if ($taskCategory->tasks()->exists()) {
            abort(409, 'This task category is used by a task and cannot be deleted.');
        }

        $taskCategory->delete();
    }

    /**
     * Resequences every row to $orderedIds' order and returns the fresh,
     * complete, ordered list (AC-049). See
     * App\Services\Lookups\LookupOrderManager::reorder() for the validation
     * rules: with no system row to pin, $orderedIds must be EXACTLY the
     * table's id set.
     *
     * @param  array<int, int>  $orderedIds
     * @return EloquentCollection<int, TaskCategory>
     */
    public function reorder(array $orderedIds): EloquentCollection
    {
        /** @var EloquentCollection<int, TaskCategory> $reordered */
        $reordered = $this->orderManager->reorder(TaskCategory::class, $orderedIds);

        return $reordered;
    }

    /**
     * Minimal, searchable, paginated task category list for the for-select
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
        $base = TaskCategory::query()->select(self::FOR_SELECT_COLUMNS);

        if (! $query->includeInactive) {
            $base->where('is_active', true);
        }

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, TaskCategory> $page */
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
     * `is_active` filter (a Task keeps showing its current task category even
     * after it is deactivated), same projection applies. Total is unaffected.
     *
     * @param  Collection<int, TaskCategory>  $page
     * @return Collection<int, TaskCategory>
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

        /** @var Collection<int, TaskCategory> $hydrated */
        $hydrated = TaskCategory::query()
            ->select(self::FOR_SELECT_COLUMNS)
            ->whereIn('id', $missingIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
