<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\TaskStatusGroup;
use App\Enums\TaskStatusSystemKey;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * D-8: the moment a Commessa itself is force-closed (create with the
 * commessa already closed, or an update flipping `is_force_closed`
 * false->true), every one of its NOT-yet-closed Tasks — root and sub-task
 * alike — is force-closed alongside it. A SYSTEM effect of
 * App\Services\WorkOrderService::create()/update(), never a user-facing Task
 * action: it deliberately bypasses every domain guard (TaskWriteLock,
 * TaskClosureFeedbackGuard, the open-subtask check, validation) and every
 * side-effect a real completion carries (no notification, no time entry),
 * and it ignores App\Services\Tasks\TaskVisibilityScope entirely — it closes
 * tasks the acting user cannot even see (AC-022). Only the per-Task activity
 * log survives, via Task's own LogsModelActivity: each row is saved
 * individually so the trait sees a real, dirty save per Task, never a bulk
 * UPDATE.
 *
 * Reopening the Commessa afterwards has NO effect on these Tasks (D-8,
 * out-of-scope note): nothing here or in WorkOrderService reopens them.
 */
final class WorkOrderTaskForceCloser
{
    /**
     * Force-closes every open Task of $workOrder and returns how many were
     * touched. Must run inside the SAME transaction as the Commessa's own
     * `is_force_closed` write (D-8).
     */
    public function closeOpenTasks(WorkOrder $workOrder, ?string $reason): int
    {
        $closedStatusId = $this->systemStatusId(TaskStatusSystemKey::ClosedNegative);
        $today = now()->toDateString();
        $closedCount = 0;

        $this->openTasksQuery($workOrder)->each(function (Task $task) use ($closedStatusId, $today, $reason, &$closedCount): void {
            $task->task_status_id = $closedStatusId;
            $task->completion_date = $today;
            $task->is_blocked = false;

            // D-8: only fills a MISSING feedback — a task that already
            // carries one (its own, from a real completion attempt) keeps
            // it untouched.
            if (blank($task->closure_feedback)) {
                $task->closure_feedback = $reason;
            }

            $task->save();
            $closedCount++;
        });

        return $closedCount;
    }

    /**
     * `open_tasks_count` on the Commessa detail (WorkOrderResource, D-8/
     * AC-023): the exact count closeOpenTasks() would touch, read-only and
     * off the SAME query, so the two can never disagree.
     */
    public function countOpenTasks(WorkOrder $workOrder): int
    {
        return $this->openTasksQuery($workOrder)->count();
    }

    /**
     * Every Task of $workOrder — root or sub-task, WITHOUT
     * TaskVisibilityScope — whose status sits in a non-closing phase
     * (open/pending/in_validation), regardless of `is_blocked`.
     *
     * @return Builder<Task>
     */
    private function openTasksQuery(WorkOrder $workOrder): Builder
    {
        return Task::query()
            ->where('work_order_id', $workOrder->id)
            ->whereHas('taskStatus', function (Builder $status): void {
                $status->whereNotIn('group', [
                    TaskStatusGroup::ClosedPositive->value,
                    TaskStatusGroup::ClosedNegative->value,
                ]);
            });
    }

    private function systemStatusId(TaskStatusSystemKey $key): int
    {
        return (int) TaskStatus::query()->where('system_key', $key->value)->value('id');
    }
}
