<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\Task;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use App\Services\Tasks\TaskTimeEntryStageRealigner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * `stage_position` placement/resequencing for a commessa's task board (spec
 * 0146, D-2/D-3): the single place that ever writes `work_order_stage_id`/
 * `stage_position` on a root Task, so the two columns can never drift out of
 * the "compact 0..n-1 per group" invariant the data_contract requires
 * (AC-013).
 *
 * A "group" is every root Task of one commessa sharing one
 * `work_order_stage_id` value, including `null` ("Senza fase") — Eloquent's
 * `where('work_order_stage_id', $stageId)` already degrades to `whereNull`
 * when `$stageId` is null, so every method below treats the two cases
 * uniformly rather than branching on it.
 *
 * `appendToStage()` is reused verbatim by `App\Services\TaskService` (spec
 * 0146, D-3/AC-015): a Task's own create/update path accodas it whenever the
 * SUBMITTED `work_order_stage_id` changes, the exact same placement rule the
 * board's own "+ Task" and `move()` use.
 */
final class TaskStagePositioner
{
    public function __construct(private readonly TaskTimeEntryStageRealigner $stageRealigner) {}

    /**
     * Places $task at the END of $stage's group (or "Senza fase" when
     * $stage is null) — spec 0146 D-2/D-3: every create, and every update
     * that changes the stage, accodas rather than lets the caller pick a
     * position. Mutates $task in memory only; the caller persists it (the
     * same convention as the other Task guards in `App\Services\TaskService`,
     * which all mutate-then-save once at the end of the transaction).
     */
    public function appendToStage(WorkOrder $workOrder, ?WorkOrderStage $stage, Task $task): void
    {
        $maxPosition = $this->groupQuery($workOrder, $stage?->id)
            ->when($task->exists, fn ($query) => $query->whereKeyNot($task->getKey()))
            ->max('stage_position');

        $task->work_order_stage_id = $stage?->id;
        $task->stage_position = $maxPosition === null ? 0 : $maxPosition + 1;
    }

    /**
     * Moves $task to $destinationStage (null = "Senza fase") at
     * zero-based $position within that group, counted AFTER $task is taken
     * out of its origin group (data_contract, POST task-board/move). Both
     * groups are left compact (0..n-1); a same-group move (reorder) is one
     * splice, a cross-group move re-splices both. Runs inside its own
     * transaction with `lockForUpdate` on the commessa row (constraints:
     * concurrent moves/reorders must serialize).
     *
     * @return array<int, array{id: int, work_order_stage_id: int|null, stage_position: int}>
     */
    public function move(WorkOrder $workOrder, Task $task, ?WorkOrderStage $destinationStage, int $position): array
    {
        return DB::transaction(function () use ($workOrder, $task, $destinationStage, $position): array {
            WorkOrder::query()->whereKey($workOrder->id)->lockForUpdate()->first();

            $originStageId = $task->work_order_stage_id;
            $destinationStageId = $destinationStage?->id;

            if ($originStageId === $destinationStageId) {
                $this->spliceWithinGroup($workOrder, $task, $originStageId, $position);

                return $this->groupRows($workOrder, [$originStageId]);
            }

            $this->removeFromGroup($workOrder, $task, $originStageId);
            $this->insertIntoGroup($workOrder, $task, $destinationStageId, $position);

            // Spec 0167, D-2/AC-002: a CROSS-fase move realigns $task's own
            // segnatempo subtree onto the destination fase — insertIntoGroup()
            // wrote the column via the query builder, so $task's in-memory
            // copy is refreshed here first (it is the root, TaskEffectiveStage
            // reads it directly, no extra query).
            $task->work_order_stage_id = $destinationStageId;
            $this->stageRealigner->realign($task);

            return $this->groupRows($workOrder, [$originStageId, $destinationStageId]);
        });
    }

    /**
     * Demotes every root Task of $stage to "Senza fase", appended at the end
     * of that group in their current relative order (spec 0146, D-2/AC-005:
     * deleting a stage never deletes or reorders its tasks, only unstages
     * them). Called BEFORE the stage row itself is deleted — by the time the
     * FK's own `nullOnDelete` would fire, every affected row already carries
     * a compact, collision-free position, so the schema-level cascade is a
     * no-op backstop rather than the mechanism actually used here.
     */
    public function demoteToUnstaged(WorkOrder $workOrder, WorkOrderStage $stage): void
    {
        $nextPosition = $this->groupQuery($workOrder, null)->max('stage_position');
        $nextPosition = $nextPosition === null ? 0 : $nextPosition + 1;

        $taskIds = $this->groupQuery($workOrder, $stage->id)->orderBy('stage_position')->pluck('id');

        foreach ($taskIds as $taskId) {
            Task::query()->whereKey($taskId)->update([
                'work_order_stage_id' => null,
                'stage_position' => $nextPosition,
            ]);
            $nextPosition++;
        }
    }

    /**
     * Reorders $task within its OWN group to $position — the same-group
     * branch of move(): remove, then reinsert at the clamped index.
     */
    private function spliceWithinGroup(WorkOrder $workOrder, Task $task, ?int $stageId, int $position): void
    {
        $ids = array_values(array_diff($this->orderedIds($workOrder, $stageId), [$task->id]));
        $position = max(0, min($position, count($ids)));
        array_splice($ids, $position, 0, [$task->id]);

        $this->reindex($ids);
    }

    /**
     * Removes $task from $stageId's group and compacts what remains — the
     * ORIGIN half of a cross-group move().
     */
    private function removeFromGroup(WorkOrder $workOrder, Task $task, ?int $stageId): void
    {
        $ids = array_values(array_diff($this->orderedIds($workOrder, $stageId), [$task->id]));

        $this->reindex($ids);
    }

    /**
     * Inserts $task into $stageId's group at the clamped $position and
     * compacts the result — the DESTINATION half of a cross-group move().
     * The group's current members are read BEFORE the FK write below: doing
     * it after would make $task match its own new `work_order_stage_id`
     * already, so `orderedIds()` would return it a second time and the
     * splice would duplicate it in the reindexed list.
     */
    private function insertIntoGroup(WorkOrder $workOrder, Task $task, ?int $stageId, int $position): void
    {
        $ids = $this->orderedIds($workOrder, $stageId);
        $position = max(0, min($position, count($ids)));
        array_splice($ids, $position, 0, [$task->id]);

        Task::query()->whereKey($task->id)->update(['work_order_stage_id' => $stageId]);

        $this->reindex($ids);
    }

    /**
     * Writes `stage_position` 0..n-1 for $orderedIds, in that exact order.
     *
     * @param  array<int, int>  $orderedIds
     */
    private function reindex(array $orderedIds): void
    {
        foreach ($orderedIds as $position => $id) {
            Task::query()->whereKey($id)->update(['stage_position' => $position]);
        }
    }

    /**
     * @return array<int, int>
     */
    private function orderedIds(WorkOrder $workOrder, ?int $stageId): array
    {
        return $this->groupQuery($workOrder, $stageId)->orderBy('stage_position')->pluck('id')->all();
    }

    /**
     * The rows of every group in $stageIds, in their FRESH (already
     * reindexed) order — move()'s own response shape, deduplicated since a
     * same-group move only ever touches one.
     *
     * @param  array<int, int|null>  $stageIds
     * @return array<int, array{id: int, work_order_stage_id: int|null, stage_position: int}>
     */
    private function groupRows(WorkOrder $workOrder, array $stageIds): array
    {
        $rows = [];

        foreach (array_unique($stageIds, SORT_REGULAR) as $stageId) {
            foreach ($this->groupQuery($workOrder, $stageId)->orderBy('stage_position')->get() as $task) {
                $rows[] = [
                    'id' => $task->id,
                    'work_order_stage_id' => $task->work_order_stage_id,
                    'stage_position' => $task->stage_position,
                ];
            }
        }

        return $rows;
    }

    /**
     * @return Builder<Task>
     */
    private function groupQuery(WorkOrder $workOrder, ?int $stageId): Builder
    {
        return Task::query()
            ->where('work_order_id', $workOrder->id)
            ->whereNull('parent_task_id')
            ->where('work_order_stage_id', $stageId);
    }
}
