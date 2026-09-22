<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\TaskStatusGroup;
use App\Exceptions\WorkOrders\WorkOrderStageHasOpenTasksException;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

/**
 * CRUD + lifecycle for a commessa's own "Fasi" (spec 0146, D-2/D-4):
 * create/rename/delete/reorder/close/reopen. Every write is gated by
 * `WorkOrderClosedGuard` (D-9): a closed commessa refuses every mutation with
 * 409 regardless of which one is attempted.
 *
 * `close()`/`reopen()` are the two that carry no `DB::transaction()` of their
 * own: a single-row `save()` needs none, and `close()`'s own guard
 * (`WorkOrderStageHasOpenTasksException`) must be evaluated and thrown BEFORE
 * any write — there is nothing to roll back either way.
 */
final class WorkOrderStageService
{
    public function __construct(private readonly TaskStagePositioner $positioner) {}

    /**
     * @return Collection<int, WorkOrderStage>
     */
    public function list(WorkOrder $workOrder): Collection
    {
        return $workOrder->stages()->get();
    }

    /**
     * D-2: a new stage is always accoded at the end, regardless of gaps a
     * previous delete may have left in `sort_order` — `max()+1` rather than
     * `count()`, so a deleted middle stage can never make a later append
     * collide with a surviving one.
     */
    public function create(WorkOrder $workOrder, string $name): WorkOrderStage
    {
        return DB::transaction(function () use ($workOrder, $name): WorkOrderStage {
            WorkOrderClosedGuard::assertOpen($workOrder);

            return $workOrder->stages()->create([
                'name' => $name,
                'sort_order' => $this->nextSortOrder($workOrder),
            ]);
        });
    }

    public function rename(WorkOrder $workOrder, WorkOrderStage $stage, string $name): WorkOrderStage
    {
        WorkOrderClosedGuard::assertOpen($workOrder);

        $stage->update(['name' => $name]);

        return $stage;
    }

    /**
     * D-2/AC-005: the stage's own root Tasks are demoted to "Senza fase",
     * appended at the end of that group in their current relative order —
     * never deleted, never reordered relative to each other. The demotion
     * runs BEFORE the delete so every affected row already sits in a
     * compact, collision-free position by the time the schema's own
     * `nullOnDelete` would otherwise fire (which becomes a no-op, since
     * nothing still references the stage).
     */
    public function delete(WorkOrder $workOrder, WorkOrderStage $stage): void
    {
        DB::transaction(function () use ($workOrder, $stage): void {
            WorkOrderClosedGuard::assertOpen($workOrder);

            $this->positioner->demoteToUnstaged($workOrder, $stage);
            $stage->delete();
        });
    }

    /**
     * Resequences every stage of $workOrder to $stageIds' order (AC-006):
     * $stageIds must be EXACTLY the commessa's own stage id set — no
     * duplicate, none missing, no foreign id — validated here regardless of
     * the FormRequest's own shape-level `distinct` rule (defense in depth,
     * the same posture as `App\Services\Statuses\StatusOrderManager::reorder()`).
     *
     * @param  array<int, int>  $stageIds
     * @return Collection<int, WorkOrderStage>
     */
    public function reorder(WorkOrder $workOrder, array $stageIds): Collection
    {
        return DB::transaction(function () use ($workOrder, $stageIds): Collection {
            WorkOrderClosedGuard::assertOpen($workOrder);
            $this->assertValidPermutation($workOrder, $stageIds);

            foreach ($stageIds as $sortOrder => $id) {
                WorkOrderStage::query()->whereKey($id)->update(['sort_order' => $sortOrder]);
            }

            return $workOrder->stages()->get();
        });
    }

    /**
     * D-4/AC-008: refuses with a domain exception (carrying the exact count,
     * for the frozen 409 body) when $stage still holds a non-closed Task,
     * root or sub-task alike.
     */
    public function close(WorkOrder $workOrder, WorkOrderStage $stage, User $actor): WorkOrderStage
    {
        WorkOrderClosedGuard::assertOpen($workOrder);

        $openTasksCount = $this->countOpenTasks($stage);

        if ($openTasksCount > 0) {
            throw new WorkOrderStageHasOpenTasksException($openTasksCount);
        }

        $stage->closed_at = now();
        $stage->closed_by_id = $actor->id;
        $stage->save();

        return $stage;
    }

    public function reopen(WorkOrder $workOrder, WorkOrderStage $stage): WorkOrderStage
    {
        WorkOrderClosedGuard::assertOpen($workOrder);

        $stage->closed_at = null;
        $stage->closed_by_id = null;
        $stage->save();

        return $stage;
    }

    private function nextSortOrder(WorkOrder $workOrder): int
    {
        $max = $workOrder->stages()->max('sort_order');

        return $max === null ? 0 : $max + 1;
    }

    /**
     * @param  array<int, int>  $stageIds
     */
    private function assertValidPermutation(WorkOrder $workOrder, array $stageIds): void
    {
        if (count($stageIds) !== count(array_unique($stageIds))) {
            abort(422, 'stage_ids contains duplicate ids.');
        }

        $currentIds = $workOrder->stages()->pluck('id')->all();

        if (array_diff($stageIds, $currentIds) !== [] || array_diff($currentIds, $stageIds) !== []) {
            abort(422, 'stage_ids must contain exactly the stages of this commessa (no missing, no foreign id).');
        }
    }

    /**
     * D-4/AC-008: every Task closing $stage would have to answer for — its
     * own root Tasks PLUS every descendant sub-task, at any depth, found by
     * walking `parent_task_id` one level at a time (one query per level,
     * never per row) since a Task's hierarchy carries no depth limit
     * (`App\Models\Task::subtasks()`).
     */
    private function countOpenTasks(WorkOrderStage $stage): int
    {
        $taskIds = $this->stageTaskIds($stage);

        if ($taskIds->isEmpty()) {
            return 0;
        }

        return Task::query()
            ->whereIn('id', $taskIds)
            ->whereHas('taskStatus', function (Builder $status): void {
                $status->whereNotIn('group', [
                    TaskStatusGroup::ClosedPositive->value,
                    TaskStatusGroup::ClosedNegative->value,
                ]);
            })
            ->count();
    }

    /**
     * @return BaseCollection<int, int>
     */
    private function stageTaskIds(WorkOrderStage $stage): BaseCollection
    {
        $ids = Task::query()->where('work_order_stage_id', $stage->id)->pluck('id');
        $frontier = $ids;

        while ($frontier->isNotEmpty()) {
            $children = Task::query()->whereIn('parent_task_id', $frontier)->pluck('id');
            $ids = $ids->merge($children);
            $frontier = $children;
        }

        return $ids;
    }
}
