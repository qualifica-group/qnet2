<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;

/**
 * Breadth-first eager load of a Task's ENTIRE sub-task sub-tree (spec 0161,
 * D-3: "tutti i livelli" — no depth cap, unlike TaskSubtaskBatchCreator's own
 * bulk-create limit of 3, D-1). `Model::preventLazyLoading()` is on in tests,
 * so recursing into `$task->subtasks` needs every level loaded FIRST — a
 * fixed-depth chain of dotted `with()` strings would silently stop working
 * the day a sub-task is manually nested past that depth (TaskHierarchyGuard
 * imposes no general limit, per spec 0101 D-12's own docblock), so this
 * walks level by level instead, stopping only once a level has no children
 * left to load.
 */
final class TaskSubtaskTreeLoader
{
    /**
     * @param  array<int, string>  $siblingRelations  extra relations to load
     *                                                alongside `subtasks` at every level (e.g. `assignees`,
     *                                                `taskStatus`) — `subtasks` itself is added automatically.
     */
    public static function load(Task $root, array $siblingRelations = []): void
    {
        $relations = ['subtasks', ...$siblingRelations];
        $frontier = Collection::make([$root]);

        while ($frontier->isNotEmpty()) {
            $frontier->loadMissing($relations);

            $frontier = Collection::make(array_merge(
                [],
                ...$frontier->map(static fn (Task $task): array => $task->subtasks->all())->all(),
            ));
        }
    }
}
