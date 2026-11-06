<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderProgressEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * List/create/delete of a commessa's manual progress entries (spec 0321).
 * Like the legacy `avanzamento_commesse`, an entry stores the INCREMENT: the
 * new total typed by the user minus the SUM already reached (the "above the
 * total" rule is validated in the FormRequest). No date or closed-commessa rule.
 */
final class WorkOrderProgressEntryService
{
    private const int FULL_PROGRESS = 100;

    /**
     * @return Collection<int, WorkOrderProgressEntry>
     */
    public function list(WorkOrder $workOrder): Collection
    {
        return $workOrder->progressEntries()->with('creator')->get();
    }

    /**
     * @param  int  $total  the new total progress ("% Stato attuale"), above the current SUM
     * @param  ?User  $actor  null only outside a request (seeders, imports)
     */
    public function create(WorkOrder $workOrder, int $total, string $recordedOn, ?User $actor): WorkOrderProgressEntry
    {
        $current = $this->currentTotal($workOrder);

        $entry = $workOrder->progressEntries()->create([
            'percentage' => $total - $current,
            'recorded_on' => $recordedOn,
            'created_by' => $actor?->id,
        ]);

        return $entry->load('creator');
    }

    /**
     * Spec 0323 D-6: lifts the total up to $total with an entry dated today,
     * or does nothing when the commessa already reached it (a null/lower
     * target never produces a negative or zero entry).
     */
    public function raiseTo(WorkOrder $workOrder, ?int $total, ?User $actor): ?WorkOrderProgressEntry
    {
        if ($total === null || $total <= $this->currentTotal($workOrder)) {
            return null;
        }

        return $this->create($workOrder, $total, now()->toDateString(), $actor);
    }

    /**
     * Spec 0324 revision 2026-11-05: a forced closure that just landed on a
     * positive (`completed`-group) status brings the progress to 100% with an
     * entry dated today; a negative closure, or no closing transition, leaves it.
     */
    public function completeOnPositiveClosure(WorkOrder $workOrder, bool $wasForceClosed, ?User $actor): void
    {
        if ($wasForceClosed
            || ! $workOrder->is_force_closed
            || $workOrder->workOrderWorkflowStatus()->value('group') !== WorkOrderStatus::Completed) {
            return;
        }

        $this->raiseTo($workOrder, self::FULL_PROGRESS, $actor);
    }

    /**
     * Spec 0323 D-2: a manual status change that carries a positive percentage
     * must go strictly above the progress already reached (legacy rule).
     *
     * @throws ValidationException
     */
    public function assertStatusPercentageAboveCurrent(WorkOrder $workOrder, ?int $percentage): void
    {
        $current = $this->currentTotal($workOrder);

        if ($percentage !== null && $percentage > 0 && $percentage <= $current) {
            throw ValidationException::withMessages([
                'work_order_workflow_status_id' => [
                    __('work_orders.workflow_status.progress_not_above_current', ['percentage' => $percentage, 'current' => $current]),
                ],
            ]);
        }
    }

    private function currentTotal(WorkOrder $workOrder): int
    {
        return (int) $workOrder->progressEntries()->reorder()->sum('percentage');
    }

    public function delete(WorkOrderProgressEntry $entry): void
    {
        $entry->delete();
    }
}
