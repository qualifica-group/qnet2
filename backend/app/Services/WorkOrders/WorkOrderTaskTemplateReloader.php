<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\TaskStatusGroup;
use App\Exceptions\WorkOrders\WorkOrderTaskTemplateReloadBlockedException;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use App\Services\WorkOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Loads or reloads a commessa's Modello di Task (spec 0399): the tasks of the
 * previous template (D-2) are deleted and the new template's generated, in
 * one transaction, unless one of them is already completed (D-3, 409).
 */
final class WorkOrderTaskTemplateReloader
{
    private const array CLOSING_GROUPS = [
        TaskStatusGroup::ClosedPositive->value,
        TaskStatusGroup::ClosedNegative->value,
    ];

    public function __construct(
        private readonly WorkOrderTaskGenerator $generator,
        private readonly WorkOrderService $workOrders,
    ) {}

    public function handle(WorkOrder $workOrder, TaskTemplate $template, User $actor): WorkOrder
    {
        DB::transaction(function () use ($workOrder, $template, $actor): void {
            // Step 1: serialize concurrent reloads of the same commessa, then the closed veto (D-5).
            WorkOrder::query()->whereKey($workOrder->id)->lockForUpdate()->first();
            WorkOrderClosedGuard::assertOpen($workOrder);

            // Step 2: the previous template's tasks, sub-tasks included (D-2); none may be completed (D-3).
            $levels = $this->templateTaskLevels($workOrder);
            $this->assertNoneCompleted($levels);

            // Step 3: delete them leaves first, then the stages they leave empty (D-4).
            $stageIds = $this->deleteTasks($levels);
            $this->deleteEmptiedStages($stageIds);

            // Step 4: link the new template and generate its tasks (D-6).
            $workOrder->task_template_id = $template->id;
            $workOrder->save();
            $workOrder->unsetRelation('stages');
            $this->generator->generate($workOrder, $template, $actor);
        });

        $workOrder->unsetRelation('taskTemplate');

        return $this->workOrders->loadDetail($workOrder);
    }

    /**
     * The template-generated tasks of $workOrder, then each level of their
     * descendants, one query per level (a Task's hierarchy has no depth limit).
     *
     * @return array<int, array<int, int>> task ids per level, roots first
     */
    private function templateTaskLevels(WorkOrder $workOrder): array
    {
        $levels = [];
        $frontier = Task::query()
            ->where('work_order_id', $workOrder->id)
            ->where('is_template_generated', true)
            ->pluck('id')
            ->all();
        $seen = [];

        while ($frontier !== []) {
            // A template-generated task can sit under another one: never collect it twice.
            $frontier = array_values(array_diff($frontier, $seen));

            if ($frontier === []) {
                break;
            }

            $levels[] = $frontier;
            $seen = [...$seen, ...$frontier];
            $frontier = Task::query()->whereIn('parent_task_id', $frontier)->pluck('id')->all();
        }

        return $levels;
    }

    /**
     * @param  array<int, array<int, int>>  $levels
     */
    private function assertNoneCompleted(array $levels): void
    {
        $taskIds = array_merge([], ...$levels);

        if ($taskIds === []) {
            return;
        }

        $completedCount = Task::query()
            ->whereIn('id', $taskIds)
            ->whereHas('taskStatus', fn (Builder $status) => $status->whereIn('group', self::CLOSING_GROUPS))
            ->count();

        if ($completedCount > 0) {
            throw new WorkOrderTaskTemplateReloadBlockedException($completedCount);
        }
    }

    /**
     * Deepest level first: `parent_task_id` is restrictOnDelete. Each row goes
     * through Eloquent `delete()` like TaskService::delete(), so model events
     * and the activity log still fire.
     *
     * @param  array<int, array<int, int>>  $levels
     * @return array<int, int> the stages the deleted tasks sat in
     */
    private function deleteTasks(array $levels): array
    {
        $stageIds = [];

        foreach (array_reverse($levels) as $taskIds) {
            foreach (Task::query()->whereIn('id', $taskIds)->get() as $task) {
                if ($task->work_order_stage_id !== null) {
                    $stageIds[$task->work_order_stage_id] = $task->work_order_stage_id;
                }

                $task->delete();
            }
        }

        return array_values($stageIds);
    }

    /**
     * D-4: a stage left with no task and no logged time was the previous
     * template's own; one still holding manual work stays.
     *
     * @param  array<int, int>  $stageIds
     */
    private function deleteEmptiedStages(array $stageIds): void
    {
        if ($stageIds === []) {
            return;
        }

        WorkOrderStage::query()
            ->whereIn('id', $stageIds)
            ->whereDoesntHave('tasks')
            ->whereDoesntHave('timeEntries')
            ->get()
            ->each(fn (WorkOrderStage $stage) => $stage->delete());
    }
}
