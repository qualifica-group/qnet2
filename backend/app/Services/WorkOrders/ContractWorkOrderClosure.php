<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\WorkOrderStatus;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * What closing a contract does to its commesse, as in the legacy. "Annullato"
 * closes every commessa, for good. Any other negative closure ("Disdetto")
 * stops only the work still open and remembers the status each commessa held,
 * so reopening the contract puts it back. The tasks are left alone, like in
 * the legacy: closing them could not be undone by the reopening.
 *
 * Must run inside the transaction of the contract's own write.
 */
final class ContractWorkOrderClosure
{
    private const string PREVIOUS_STATUS = 'status_before_contract_termination_id';

    /** The groups of the work a termination interrupts (legacy statuses 1 and 2). */
    private const array OPEN_GROUPS = [WorkOrderStatus::Open, WorkOrderStatus::InProgress];

    public function __construct(private readonly WorkOrderWorkflowStatusAssigner $statusAssigner) {}

    public function close(Contract $contract, ContractStatus $destination): void
    {
        $destination->closesAsTermination() ? $this->terminateOpen($contract) : $this->cancelAll($contract);
    }

    public function restore(Contract $contract): void
    {
        $this->ofContract($contract)
            ->where('is_force_closed', true)
            ->whereNotNull(self::PREVIOUS_STATUS)
            ->each(function (WorkOrder $workOrder): void {
                $previousStatusId = (int) $workOrder->getAttribute(self::PREVIOUS_STATUS);

                $workOrder->forceFill([
                    'is_force_closed' => false,
                    'force_close_reason' => null,
                    self::PREVIOUS_STATUS => null,
                ])->save();

                $this->statusAssigner->restore($workOrder, $previousStatusId);
            });
    }

    private function terminateOpen(Contract $contract): void
    {
        $this->ofContract($contract)
            ->where('is_force_closed', false)
            ->whereHas('workOrderWorkflowStatus', static function (Builder $status): void {
                $status->whereIn('group', array_column(self::OPEN_GROUPS, 'value'));
            })
            ->each(fn (WorkOrder $workOrder) => $this->forceClose(
                $workOrder,
                __('work_orders.contract_closure.terminated'),
                $workOrder->work_order_workflow_status_id,
            ));
    }

    /**
     * Commesse already closed negatively keep their own status and reason.
     */
    private function cancelAll(Contract $contract): void
    {
        $this->ofContract($contract)
            ->where(static function (Builder $notYetCancelled): void {
                $notYetCancelled->where('is_force_closed', false)
                    ->orWhereHas('workOrderWorkflowStatus', static function (Builder $status): void {
                        $status->where('group', '<>', WorkOrderStatus::Closed->value);
                    });
            })
            ->each(fn (WorkOrder $workOrder) => $this->forceClose($workOrder, __('work_orders.contract_closure.cancelled'), null));
    }

    private function forceClose(WorkOrder $workOrder, string $reason, ?int $previousStatusId): void
    {
        $workOrder->forceFill([
            'is_force_closed' => true,
            'force_close_reason' => $reason,
            self::PREVIOUS_STATUS => $previousStatusId,
        ])->save();

        // Always a closing transition: a commessa already closed positively must still reach `closed`.
        $this->statusAssigner->followForceClose($workOrder, wasForceClosed: false, logChange: true);
    }

    /**
     * @return Builder<WorkOrder>
     */
    private function ofContract(Contract $contract): Builder
    {
        return WorkOrder::query()->where('quote_id', $contract->quote_id);
    }
}
