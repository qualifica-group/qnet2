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
 * Nested (spec 0154, D-1): `parent_id` self-references this same table, with
 * an anti-cycle guard on update (a category cannot become its own parent nor
 * one of its own descendants') and a for-select projected as a depth-first
 * tree so the frontend can render it indented in a single flat list.
 *
 * The controller stays thin; this Service is the single authority.
 */
class TaskCategoryService
{
    /**
     * Defensive cap on the `parent_id` walk: the anti-cycle guard below
     * prevents a real cycle from ever being persisted, so this only guards
     * against corrupted data looping forever — mirrors
     * CategoryHierarchy::MAX_DEPTH for product categories.
     */
    private const int MAX_DEPTH_WALK = 100;

    /**
     * The projection every for-select query reads. `is_active` is part of
     * it because the resource EXPOSES it in `meta` (the reorder sheet marks
     * the deactivated rows it asked for via `include_inactive`): a column
     * left out here would silently serialize as null, not as false.
     * `parent_id` feeds the depth-first tree order and the `meta.parent_id`/
     * `meta.depth` the for-select exposes (D-1). `sort_order` is likewise
     * REQUIRED here now (unlike the plain lookups, which sort with a plain
     * SQL `ORDER BY` and never read the value back in PHP): the depth-first
     * flattening below sorts siblings in PHP off `$category->sort_order`,
     * which would silently read null — falling back to alphabetical order —
     * if the column were left unselected.
     *
     * Identity plus the badge
     * attributes the select renders (`color`/`icon`).
     *
     * @var array<int, string>
     */
    private const array FOR_SELECT_COLUMNS = ['id', 'name', 'color', 'icon', 'is_active', 'parent_id', 'sort_order'];

    public function __construct(private readonly LookupOrderManager $orderManager) {}

    /**
     * A cycle is structurally impossible on create (the row has no id yet),
     * so no anti-cycle guard is needed here — only on update().
     */
    public function create(CreateTaskCategoryData $data): TaskCategory
    {
        return TaskCategory::create([
            ...$data->attributes(),
            'sort_order' => $this->orderManager->placeNew(TaskCategory::class),
        ]);
    }

    public function update(TaskCategory $taskCategory, UpdateTaskCategoryData $data): TaskCategory
    {
        if ($data->parentIdSubmitted && $data->parentId !== null) {
            $this->assertNoCycle($taskCategory, $data->parentId);
        }

        $attributes = $data->submittedAttributes();

        // Unconditional save: fires the model's saved event even when no
        // native attribute changed, so the HasCustomFields write pipeline
        // (spec 0021) persists a custom-fields-only edit. A clean save runs
        // no UPDATE query.
        $taskCategory->fill($attributes)->save();

        return $taskCategory->fresh();
    }

    /**
     * Restrictive delete (spec 0101, D-8b; spec 0154, D-1): a task category
     * still referenced by a Task, or still parenting another category,
     * cannot be removed — never a cascade, so neither a Task nor a child
     * category is ever removed as a side effect (AC-041). Defense in depth:
     * both FKs are also restrictOnDelete at the schema layer. The generic
     * bulk-delete goes through the SAME method via
     * TaskCategoriesTableDefinition::deleteModel().
     */
    public function delete(TaskCategory $taskCategory): void
    {
        if ($taskCategory->children()->exists()) {
            abort(409, 'This task category has child categories and cannot be deleted.');
        }

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
     * Minimal, searchable task category list for the for-select standard
     * (ADR 0011), projected as a DEPTH-FIRST TREE (spec 0154, D-1): a parent
     * is immediately followed by its children, siblings ordered by
     * `sort_order` then `name` — the same order the plain (non-nested)
     * lookups use among siblings, just applied one level at a time. Every
     * eligible row carries a computed `depth` attribute (0 for roots),
     * exposed as `meta.depth` by TaskCategoryForSelectResource alongside the
     * real `parent_id` column, so the frontend can render the list indented
     * without re-deriving the tree itself.
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

        $ordered = $this->depthFirstOrder($base->get());

        if ($query->hasSearch()) {
            $needle = mb_strtolower($query->search);

            $ordered = $ordered->filter(
                static fn (TaskCategory $category): bool => str_contains(mb_strtolower($category->name), $needle),
            )->values();
        }

        $total = $ordered->count();

        $page = $ordered->slice($query->offset, $query->limit)->values();

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
     * Each one gets its own `depth`, walked from `parent_id` since it sits
     * outside the tree order built above.
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
            ->get()
            ->each(function (TaskCategory $category): void {
                $category->setAttribute('depth', count($this->ancestorIds($category->id)));
            });

        return $page->concat($hydrated);
    }

    /**
     * Depth-first flattening of $categories (spec 0154, D-1): each node
     * immediately followed by its own children, siblings ordered by
     * `sort_order` then `name`. Every returned model carries a computed
     * `depth` attribute (0 for roots) — never persisted, read-side only.
     *
     * @param  Collection<int, TaskCategory>  $categories
     * @return Collection<int, TaskCategory>
     */
    private function depthFirstOrder(Collection $categories): Collection
    {
        // groupBy() turns a null `parent_id` into the '' array key (PHP
        // cannot key an array with null) — mirrors CategoryTreeBuilder::tree().
        $byParent = $categories->groupBy(fn (TaskCategory $category): int|string => $category->parent_id ?? '');

        $ordered = collect();
        $this->appendChildren($byParent, null, 0, $ordered);

        return $ordered;
    }

    /**
     * @param  Collection<int|string, Collection<int, TaskCategory>>  $byParent
     * @param  Collection<int, TaskCategory>  $ordered
     */
    private function appendChildren(Collection $byParent, ?int $parentId, int $depth, Collection $ordered): void
    {
        if ($depth >= self::MAX_DEPTH_WALK) {
            return;
        }

        /** @var Collection<int, TaskCategory> $children */
        $children = $byParent->get($parentId ?? '', collect())
            ->sortBy([['sort_order', 'asc'], ['name', 'asc']]);

        foreach ($children as $category) {
            $category->setAttribute('depth', $depth);
            $ordered->push($category);
            $this->appendChildren($byParent, $category->id, $depth + 1, $ordered);
        }
    }

    /**
     * $parentId may not be $category itself, nor one of its own descendants
     * (i.e. $category may not be an ancestor of the prospective new parent)
     * — either would create a cycle in the tree.
     */
    private function assertNoCycle(TaskCategory $category, int $parentId): void
    {
        if ($parentId === $category->id) {
            abort(422, 'A task category cannot be its own parent.');
        }

        if (in_array($category->id, $this->ancestorIds($parentId), true)) {
            abort(422, 'A task category cannot be moved under one of its own descendants.');
        }
    }

    /**
     * $categoryId's ancestor ids, walked via `parent_id` in PHP (portable
     * across the SQLite dev/test driver and MySQL production, mirrors
     * CategoryHierarchy::ancestors() for product categories). Used both by
     * the anti-cycle guard and by the hydration depth resolution above.
     *
     * @return array<int, int>
     */
    private function ancestorIds(int $categoryId): array
    {
        $ids = [];
        $currentId = $categoryId;
        $depth = 0;

        while ($depth < self::MAX_DEPTH_WALK) {
            $current = TaskCategory::query()->select(['id', 'parent_id'])->find($currentId);

            if ($current === null || $current->parent_id === null) {
                break;
            }

            $ids[] = $current->parent_id;
            $currentId = $current->parent_id;
            $depth++;
        }

        return $ids;
    }
}
