<?php

declare(strict_types=1);

namespace App\Services\WorkOrderWorkflows;

use App\Models\Task;
use App\Models\TaskTemplateItem;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Support\Collection;

/**
 * Refuses to remove a working status something still points at (spec 0377):
 * alone, from a set, or together with its workflow. Tasks, task template rows
 * and the status kept across a contract termination count too, because their
 * foreign keys would otherwise blank the link in silence.
 */
final class WorkOrderWorkflowStatusUsageGuard
{
    private const int CONFLICT = 409;

    private const string STATUS_COLUMN = 'work_order_workflow_status_id';

    private const string PREVIOUS_STATUS_COLUMN = 'status_before_contract_termination_id';

    /**
     * @param  Collection<int, WorkOrderWorkflowStatus>  $statuses
     */
    public function assertUnused(Collection $statuses): void
    {
        $usage = $this->usageByStatusId($statuses->pluck('id'));

        if ($usage->isEmpty()) {
            return;
        }

        $labels = $statuses
            ->filter(fn (WorkOrderWorkflowStatus $status): bool => $usage->has($status->id))
            ->map(fn (WorkOrderWorkflowStatus $status): string => "{$status->name} ({$usage->get($status->id)})")
            ->implode(', ');

        abort(self::CONFLICT, __('These statuses are used by work orders or tasks and cannot be deleted: :statuses', ['statuses' => $labels]));
    }

    /**
     * @param  Collection<int, int>  $statusIds
     * @return Collection<int, int> how many records use each status id in use
     */
    private function usageByStatusId(Collection $statusIds): Collection
    {
        return collect([
            WorkOrder::query()->whereIn(self::STATUS_COLUMN, $statusIds)->pluck(self::STATUS_COLUMN),
            WorkOrder::query()->whereIn(self::PREVIOUS_STATUS_COLUMN, $statusIds)->pluck(self::PREVIOUS_STATUS_COLUMN),
            Task::query()->whereIn(self::STATUS_COLUMN, $statusIds)->pluck(self::STATUS_COLUMN),
            TaskTemplateItem::query()->whereIn(self::STATUS_COLUMN, $statusIds)->pluck(self::STATUS_COLUMN),
        ])->flatten()->countBy();
    }
}
