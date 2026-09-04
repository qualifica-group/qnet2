<?php

declare(strict_types=1);

namespace App\Services\Lookups;

use App\Models\TaskCategory;
use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * `sort_order` placement/resequencing for the PURE lookup configurators
 * (spec 0101, D-4 as rectified 2026-09-04): server-managed since the field
 * is `prohibited` in store/update, changeable ONLY through the dedicated
 * reorder endpoint (AC-045/AC-049). Generic on the model via a class-string
 * (no speculative interface — engineering.md §1.3): task_types,
 * task_categories, task_priorities and task_importances share the exact same
 * name/sort_order shape.
 *
 * WHY THIS IS NOT App\Services\Statuses\StatusOrderManager (do not "unify"
 * the two looking for symmetry): that class is `system_key`-AWARE. It pins
 * `SYSTEM_HEAD_KEYS` rows to the head and `SYSTEM_TAIL_KEYS` rows to the
 * tail, and validates a reorder set against `whereNull('system_key')`. These
 * four tables have NO `system_key` column at all (D-4) and their models
 * declare neither constant, so every one of those code paths would either
 * fatal on a missing constant or fail at the SQL layer. `task-statuses`
 * keeps using StatusOrderManager (D-5); merging the two would mean teaching
 * the status resequencer to tolerate the absence of the very concept it
 * exists to protect.
 *
 * Sequence invariant, maintained by every method here: rows sit at STEP,
 * 2*STEP, 3*STEP... in their intended order, with no pinned position — every
 * row is freely reorderable.
 */
class LookupOrderManager
{
    private const int STEP = 10;

    /**
     * The `sort_order` a brand-new row should be created with: the last
     * existing row's order + STEP (or 0 for the first row ever). Lives here,
     * next to reorder(), so placement and resequencing cannot drift apart —
     * both are the single authority on this column.
     *
     * @param  class-string<TaskType>|class-string<TaskCategory>|class-string<TaskPriority>|class-string<TaskImportance>  $modelClass
     */
    public function placeNew(string $modelClass): int
    {
        $lastOrder = $modelClass::query()->max('sort_order');

        return (int) ($lastOrder ?? -self::STEP) + self::STEP;
    }

    /**
     * Resequences every row to $orderedIds' order (10, 20, ...) and returns
     * the fresh, complete, ordered list. $orderedIds must be EXACTLY the
     * table's id set — no duplicate, no unknown id, none missing (AC-049).
     * Unlike the status resequencer there is no subset to exclude here: with
     * no system rows, "every row" IS the reorderable set.
     *
     * Validated in this method rather than only in the FormRequest (defense
     * in depth, mirroring StatusOrderManager::assertValidReorderSet): a set
     * equality against the DB is not something a validation rule can
     * express, and the guard must hold whatever the caller.
     *
     * @param  class-string<TaskType>|class-string<TaskCategory>|class-string<TaskPriority>|class-string<TaskImportance>  $modelClass
     * @param  array<int, int>  $orderedIds
     * @return Collection<int, TaskType|TaskCategory|TaskPriority|TaskImportance>
     *
     * @throws HttpException 422
     */
    public function reorder(string $modelClass, array $orderedIds): Collection
    {
        return DB::transaction(function () use ($modelClass, $orderedIds): Collection {
            $this->assertValidReorderSet($modelClass, $orderedIds);

            $sortOrder = 0;

            foreach ($orderedIds as $id) {
                $sortOrder += self::STEP;

                $modelClass::query()->where('id', $id)->update(['sort_order' => $sortOrder]);
            }

            return $modelClass::query()->orderBy('sort_order')->orderBy('name')->orderBy('id')->get();
        });
    }

    /**
     * $orderedIds must be exactly the table's id set: no duplicates, no
     * unknown id, none missing.
     *
     * @param  class-string<TaskType>|class-string<TaskCategory>|class-string<TaskPriority>|class-string<TaskImportance>  $modelClass
     * @param  array<int, int>  $orderedIds
     *
     * @throws HttpException 422
     */
    private function assertValidReorderSet(string $modelClass, array $orderedIds): void
    {
        if (count($orderedIds) !== count(array_unique($orderedIds))) {
            abort(422, 'ordered_ids contains duplicate ids.');
        }

        $existingIds = $modelClass::query()->pluck('id')->all();

        if (array_diff($orderedIds, $existingIds) !== [] || array_diff($existingIds, $orderedIds) !== []) {
            abort(422, 'ordered_ids must contain exactly every row of this configurator (none unknown, none missing).');
        }
    }
}
