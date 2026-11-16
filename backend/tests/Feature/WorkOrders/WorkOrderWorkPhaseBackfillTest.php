<?php

declare(strict_types=1);

use App\Enums\WorkOrderWorkPhase;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Spec 0395, D-6: the schema migration backfills the phase of the commesse
// already there, approximating the legacy status.

uses(RefreshDatabase::class);

function backfillStatusId(string $group): int
{
    return (WorkOrderWorkflowStatus::query()->whereNull('work_order_workflow_id')->where('group', $group)->first()
        ?? WorkOrderWorkflowStatus::factory()->global()->create(['group' => $group]))->id;
}

function backfillWorkOrder(string $group, bool $forceClosed = false, ?string $reason = null, bool $withSupervisor = false, bool $contractTerminated = false): WorkOrder
{
    $workOrder = WorkOrder::factory()->create();
    $workOrder->forceFill([
        'work_order_workflow_status_id' => backfillStatusId($group),
        'is_force_closed' => $forceClosed,
        'force_close_reason' => $forceClosed ? ($reason ?? 'Own reason') : null,
        'status_before_contract_termination_id' => $contractTerminated ? backfillStatusId('open') : null,
    ])->save();

    if ($withSupervisor) {
        $workOrder->supervisors()->attach(User::factory()->create()->id);
    }

    return $workOrder;
}

it('backfills the phase from the Responsabili, the legacy reasons and the closing status', function () {
    $cases = [
        [backfillWorkOrder('in_progress'), WorkOrderWorkPhase::ToPlan, null],
        [backfillWorkOrder('open', withSupervisor: true), WorkOrderWorkPhase::InProgress, null],
        [backfillWorkOrder('closed', true, 'Chiusa (legacy)'), WorkOrderWorkPhase::Completed, null],
        [backfillWorkOrder('completed', true, 'Annullata (legacy)'), WorkOrderWorkPhase::Cancelled, null],
        [backfillWorkOrder('closed', true, 'Disdetta (legacy)'), WorkOrderWorkPhase::Terminated, null],
        [backfillWorkOrder('closed', true, withSupervisor: true, contractTerminated: true), WorkOrderWorkPhase::Terminated, WorkOrderWorkPhase::InProgress],
        [backfillWorkOrder('completed', true), WorkOrderWorkPhase::Completed, null],
        [backfillWorkOrder('closed', true), WorkOrderWorkPhase::Cancelled, null],
    ];
    $migration = require database_path('migrations/2026_12_10_100000_add_work_phase_to_work_orders_table.php');

    $migration->down();
    $migration->up();

    foreach ($cases as [$workOrder, $phase, $previous]) {
        $workOrder->refresh();

        expect($workOrder->work_phase)->toBe($phase)
            ->and($workOrder->work_phase_before_termination)->toBe($previous);
    }
});
