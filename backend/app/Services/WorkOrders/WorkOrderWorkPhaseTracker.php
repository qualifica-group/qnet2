<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderWorkPhase;
use App\Models\Contract;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Moves the working phase of a commessa (spec 0395) with the rules of the
 * legacy `orders.status`: born to plan, in progress once a Responsabile is
 * added, completed or cancelled by a forced closure; a contract cancels all
 * its commesse or terminates the open ones, reversibly. Independent from
 * "Stato lavorazione", like in the legacy.
 */
final class WorkOrderWorkPhaseTracker
{
    private const string PHASE = 'work_phase';

    private const string PREVIOUS_PHASE = 'work_phase_before_termination';

    /** The phases a contract termination interrupts (legacy statuses 1 and 2). */
    private const array OPEN_PHASES = [WorkOrderWorkPhase::ToPlan, WorkOrderWorkPhase::InProgress];

    /**
     * After a create or update of WorkOrderService, once the Responsabili are synced.
     *
     * @param  array<int, int>  $addedSupervisorIds  the Responsabili this save just added
     */
    public function follow(WorkOrder $workOrder, bool $wasForceClosed, array $addedSupervisorIds): void
    {
        // Step 1: a forced closure or a reopening decides the phase
        if (! $wasForceClosed && $workOrder->is_force_closed) {
            $workOrder->work_phase = $this->closingPhase($workOrder);
        } elseif ($wasForceClosed && ! $workOrder->is_force_closed) {
            $workOrder->work_phase = $workOrder->supervisors()->exists() ? WorkOrderWorkPhase::InProgress : WorkOrderWorkPhase::ToPlan;
            $workOrder->work_phase_before_termination = null;
        } elseif ($addedSupervisorIds !== [] && $workOrder->work_phase === WorkOrderWorkPhase::ToPlan) {
            // Step 2: otherwise a new Responsabile starts the work (legacy AddTecnico)
            $workOrder->work_phase = WorkOrderWorkPhase::InProgress;
        }

        // Step 3: persist only a real change
        if ($workOrder->isDirty([self::PHASE, self::PREVIOUS_PHASE])) {
            $workOrder->save();
        }
    }

    /** "Annullato": every commessa of the contract, whatever its phase (legacy ConfirmDelete). */
    public function cancelContract(Contract $contract): void
    {
        $this->ofContract($contract)->update([
            self::PHASE => WorkOrderWorkPhase::Cancelled->value,
            self::PREVIOUS_PHASE => null,
        ]);
    }

    /** "Disdetto": only the open commesse, remembering their phase (legacy DisdettaCommesseService::applica). */
    public function terminateContract(Contract $contract): void
    {
        // The previous phase is assigned first: MySQL evaluates SET left to right.
        $this->ofContract($contract)
            ->whereIn(self::PHASE, array_column(self::OPEN_PHASES, 'value'))
            ->update([
                self::PREVIOUS_PHASE => DB::raw(self::PHASE),
                self::PHASE => WorkOrderWorkPhase::Terminated->value,
            ]);
    }

    /** Contract reopened: the terminated commesse return to their phase (legacy DisdettaCommesseService::revoca). */
    public function restoreContract(Contract $contract): void
    {
        $this->ofContract($contract)
            ->where(self::PHASE, WorkOrderWorkPhase::Terminated->value)
            ->update([
                self::PHASE => DB::raw('COALESCE('.self::PREVIOUS_PHASE.", '".WorkOrderWorkPhase::ToPlan->value."')"),
                self::PREVIOUS_PHASE => null,
            ]);
    }

    /** A positive closing status completes the work; a negative one cancels it. */
    private function closingPhase(WorkOrder $workOrder): WorkOrderWorkPhase
    {
        return $workOrder->workOrderWorkflowStatus()->value('group') === WorkOrderStatus::Completed
            ? WorkOrderWorkPhase::Completed
            : WorkOrderWorkPhase::Cancelled;
    }

    /**
     * @return Builder<WorkOrder>
     */
    private function ofContract(Contract $contract): Builder
    {
        return WorkOrder::query()->where('quote_id', $contract->quote_id);
    }
}
