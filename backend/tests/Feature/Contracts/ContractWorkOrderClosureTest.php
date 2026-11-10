<?php

declare(strict_types=1);

use App\DataObjects\WorkOrders\UpdateWorkOrderData;
use App\Enums\WorkOrderStatus;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflowStatus;
use App\Services\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// User request 2026-11-09: closing a contract reaches its commesse as in the
// legacy (DisdettaCommesseService, Contracts/ConfirmDelete).

uses(RefreshDatabase::class);

beforeEach(function () {
    $actor = User::factory()->create();

    foreach (['terminate', 'reactivate', 'viewAll'] as $ability) {
        $actor->givePermissionTo(Permission::findOrCreate("contracts.{$ability}"));
    }

    Sanctum::actingAs($actor);
});

function closureWorkOrder(Contract $contract, string $group, bool $forceClosed = false): WorkOrder
{
    $status = WorkOrderWorkflowStatus::query()->whereNull('work_order_workflow_id')->where('group', $group)->first()
        ?? WorkOrderWorkflowStatus::factory()->global()->create(['group' => $group]);

    $workOrder = WorkOrder::factory()->create(['quote_id' => $contract->quote_id]);
    $workOrder->forceFill([
        'work_order_workflow_status_id' => $status->id,
        'is_force_closed' => $forceClosed,
        'force_close_reason' => $forceClosed ? 'Own reason' : null,
    ])->save();

    return $workOrder;
}

function terminateContract(Contract $contract, ?string $systemKey = null): void
{
    test()->postJson("/api/contracts/{$contract->id}/terminate", [
        'terminated_at' => now()->toDateString(),
        'termination_reason' => 'Customer request',
        ...($systemKey === null ? [] : ['contract_status_id' => ContractStatus::where('system_key', $systemKey)->sole()->id]),
    ])->assertOk();
}

function reactivateContract(Contract $contract): void
{
    test()->postJson("/api/contracts/{$contract->id}/reactivate", [
        'contract_status_id' => ContractStatus::where('system_key', 'new')->sole()->id,
    ])->assertOk();
}

function closureGroup(WorkOrder $workOrder): WorkOrderStatus
{
    return $workOrder->refresh()->workOrderWorkflowStatus()->sole()->group;
}

it('a termination closes only the work still open, and only of that contract', function () {
    $contract = Contract::factory()->create();
    $open = closureWorkOrder($contract, 'open');
    $inProgress = closureWorkOrder($contract, 'in_progress');
    $completed = closureWorkOrder($contract, 'completed');
    $otherContract = closureWorkOrder(Contract::factory()->create(), 'open');

    terminateContract($contract);

    expect(closureGroup($open))->toBe(WorkOrderStatus::Closed)
        ->and($open->is_force_closed)->toBeTrue()
        ->and($open->force_close_reason)->toBe(__('work_orders.contract_closure.terminated'))
        ->and(closureGroup($inProgress))->toBe(WorkOrderStatus::Closed)
        ->and(closureGroup($completed))->toBe(WorkOrderStatus::Completed)
        ->and($completed->is_force_closed)->toBeFalse()
        ->and(closureGroup($otherContract))->toBe(WorkOrderStatus::Open)
        ->and($otherContract->is_force_closed)->toBeFalse();
});

it('a termination leaves the tasks of the commessa alone', function () {
    $contract = Contract::factory()->create();
    $workOrder = closureWorkOrder($contract, 'open');
    $task = Task::factory()->create(['work_order_id' => $workOrder->id]);
    $statusId = $task->task_status_id;

    terminateContract($contract);

    expect($task->refresh()->task_status_id)->toBe($statusId);
});

it('reopening a terminated contract restores the status each commessa held', function () {
    $contract = Contract::factory()->create();
    $inProgress = closureWorkOrder($contract, 'in_progress');
    $statusId = $inProgress->work_order_workflow_status_id;
    $closedBefore = closureWorkOrder($contract, 'closed', forceClosed: true);

    terminateContract($contract);
    reactivateContract($contract);

    expect($inProgress->refresh()->work_order_workflow_status_id)->toBe($statusId)
        ->and($inProgress->is_force_closed)->toBeFalse()
        ->and($inProgress->force_close_reason)->toBeNull()
        ->and($inProgress->getAttribute('status_before_contract_termination_id'))->toBeNull()
        ->and($closedBefore->refresh()->is_force_closed)->toBeTrue()
        ->and($closedBefore->force_close_reason)->toBe('Own reason');
});

it('a commessa reopened by hand is no longer touched by the contract reopening', function () {
    $contract = Contract::factory()->create();
    $workOrder = closureWorkOrder($contract, 'in_progress');

    terminateContract($contract);
    app(WorkOrderService::class)->update($workOrder->refresh(), UpdateWorkOrderData::fromValidated(['is_force_closed' => false]));

    expect($workOrder->refresh()->getAttribute('status_before_contract_termination_id'))->toBeNull();
});

it('a cancellation closes every commessa, the concluded ones too, for good', function () {
    $contract = Contract::factory()->create();
    $open = closureWorkOrder($contract, 'open');
    $completed = closureWorkOrder($contract, 'completed', forceClosed: true);
    $closedBefore = closureWorkOrder($contract, 'closed', forceClosed: true);

    terminateContract($contract, 'cancelled');

    expect(closureGroup($open))->toBe(WorkOrderStatus::Closed)
        ->and($open->force_close_reason)->toBe(__('work_orders.contract_closure.cancelled'))
        ->and(closureGroup($completed))->toBe(WorkOrderStatus::Closed)
        ->and($completed->is_force_closed)->toBeTrue()
        ->and($closedBefore->refresh()->force_close_reason)->toBe('Own reason');

    reactivateContract($contract);

    expect($open->refresh()->is_force_closed)->toBeTrue()
        ->and(closureGroup($completed))->toBe(WorkOrderStatus::Closed);
});
