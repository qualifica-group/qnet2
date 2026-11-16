<?php

declare(strict_types=1);

use App\Enums\WorkOrderWorkPhase;
use App\Models\BusinessFunction;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0395: the working phase of the Commesse tabs follows the rules of the
// legacy `orders.status` (AC-001..AC-004).

uses(RefreshDatabase::class);

function lifecyclePhase(WorkOrder $workOrder): WorkOrderWorkPhase
{
    return $workOrder->refresh()->work_phase;
}

function lifecycleWorkOrder(WorkOrderWorkPhase $phase, array $attributes = []): WorkOrder
{
    $workOrder = WorkOrder::factory()->create($attributes);
    $workOrder->forceFill(['work_phase' => $phase])->save();

    return $workOrder;
}

/**
 * A commessa on the open row of its own workflow set, so a forced closure can pick a closing status.
 *
 * @return array{0: WorkOrder, 1: WorkOrderWorkflow}
 */
function lifecycleOnOwnWorkflow(): array
{
    $function = BusinessFunction::factory()->create();
    $workflow = workOrderWorkflowFor('By function', ['business_function_id' => $function->id]);
    $line = QuoteLine::factory()->create([
        'product_id' => Product::factory()->create([
            'category_id' => ProductCategory::factory()->create(['business_function_id' => $function->id])->id,
        ])->id,
    ]);
    $workOrder = WorkOrder::factory()->create(['quote_id' => $line->quote_id]);
    $workOrder->quoteLines()->attach($line->id);
    $workOrder->forceFill(['work_order_workflow_status_id' => $workflow->statuses()->where('system_key', 'open')->value('id')])->save();

    return [$workOrder, $workflow];
}

function lifecycleCreate(array $supervisorIds): WorkOrder
{
    $actor = User::factory()->create();
    $actor->givePermissionTo([Permission::findOrCreate('work-orders.create', 'web'), Permission::findOrCreate('work-orders.view', 'web')]);
    Sanctum::actingAs($actor);

    $id = test()->postJson('/api/work-orders', [
        'quote_id' => Quote::factory()->create()->id, 'title' => 'Nuova', 'type' => 'processing',
        'start_date' => '2026-11-14', 'supervisor_ids' => $supervisorIds,
    ])->assertCreated()->json('data.id');

    return WorkOrder::query()->findOrFail($id);
}

function lifecycleTerminate(Contract $contract, string $systemKey): void
{
    test()->postJson("/api/contracts/{$contract->id}/terminate", [
        'terminated_at' => now()->toDateString(),
        'termination_reason' => 'Customer request',
        'contract_status_id' => ContractStatus::where('system_key', $systemKey)->sole()->id,
    ])->assertOk();
}

function lifecycleContractActor(): void
{
    $actor = User::factory()->create();

    foreach (['terminate', 'reactivate', 'viewAll'] as $ability) {
        $actor->givePermissionTo(Permission::findOrCreate("contracts.{$ability}", 'web'));
    }

    Sanctum::actingAs($actor);
}

it('AC-001: a new commessa is to plan, or in progress when born with a Responsabile', function () {
    expect(lifecyclePhase(lifecycleCreate([])))->toBe(WorkOrderWorkPhase::ToPlan)
        ->and(lifecyclePhase(lifecycleCreate([User::factory()->create()->id])))->toBe(WorkOrderWorkPhase::InProgress);
});

it('AC-002: adding a Responsabile starts the work only of a commessa still to plan', function (WorkOrderWorkPhase $from, WorkOrderWorkPhase $to) {
    $workOrder = lifecycleWorkOrder($from);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['supervisor_ids' => [User::factory()->create()->id]])->assertOk();

    expect(lifecyclePhase($workOrder))->toBe($to);
})->with([
    'to plan' => [WorkOrderWorkPhase::ToPlan, WorkOrderWorkPhase::InProgress],
    'in progress' => [WorkOrderWorkPhase::InProgress, WorkOrderWorkPhase::InProgress],
    'completed' => [WorkOrderWorkPhase::Completed, WorkOrderWorkPhase::Completed],
]);

it('AC-002: any other change leaves the phase alone', function () {
    $workOrder = lifecycleWorkOrder(WorkOrderWorkPhase::ToPlan);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['title' => 'Rinominata', 'supervisor_ids' => []])->assertOk();

    expect(lifecyclePhase($workOrder))->toBe(WorkOrderWorkPhase::ToPlan);
});

it('AC-003: a forced closure completes or cancels by the closing status', function (string $systemKey, WorkOrderWorkPhase $expected) {
    [$workOrder, $workflow] = lifecycleOnOwnWorkflow();
    Sanctum::actingAs(workOrderUserWith(['update']));

    $this->patchJson("/api/work-orders/{$workOrder->id}", [
        'is_force_closed' => true,
        'force_close_reason' => 'Fine',
        'force_close_status_id' => $workflow->statuses()->where('system_key', $systemKey)->value('id'),
    ])->assertOk();

    expect(lifecyclePhase($workOrder))->toBe($expected);
})->with([
    'positive' => ['completed', WorkOrderWorkPhase::Completed],
    'negative' => ['closed', WorkOrderWorkPhase::Cancelled],
]);

it('AC-003: reopening goes back to in progress with a Responsabile, to plan without', function (bool $withSupervisor, WorkOrderWorkPhase $expected) {
    [$workOrder] = lifecycleOnOwnWorkflow();
    $workOrder->forceFill(['is_force_closed' => true, 'force_close_reason' => 'Fine', 'work_phase' => WorkOrderWorkPhase::Terminated, 'work_phase_before_termination' => WorkOrderWorkPhase::ToPlan])->save();

    if ($withSupervisor) {
        $workOrder->supervisors()->attach(User::factory()->create()->id);
    }

    Sanctum::actingAs(workOrderUserWith(['update']));
    $this->patchJson("/api/work-orders/{$workOrder->id}", ['is_force_closed' => false])->assertOk();

    expect(lifecyclePhase($workOrder))->toBe($expected)
        ->and($workOrder->work_phase_before_termination)->toBeNull();
})->with([
    'with a Responsabile' => [true, WorkOrderWorkPhase::InProgress],
    'without' => [false, WorkOrderWorkPhase::ToPlan],
]);

it('AC-004: a cancelled contract cancels every commessa, a terminated one only the open ones', function () {
    lifecycleContractActor();
    $cancelled = Contract::factory()->create();
    $cancelledDone = lifecycleWorkOrder(WorkOrderWorkPhase::Completed, ['quote_id' => $cancelled->quote_id]);
    $cancelledOpen = lifecycleWorkOrder(WorkOrderWorkPhase::InProgress, ['quote_id' => $cancelled->quote_id]);
    $terminated = Contract::factory()->create();
    $toPlan = lifecycleWorkOrder(WorkOrderWorkPhase::ToPlan, ['quote_id' => $terminated->quote_id]);
    $inProgress = lifecycleWorkOrder(WorkOrderWorkPhase::InProgress, ['quote_id' => $terminated->quote_id]);
    $done = lifecycleWorkOrder(WorkOrderWorkPhase::Completed, ['quote_id' => $terminated->quote_id]);
    $elsewhere = lifecycleWorkOrder(WorkOrderWorkPhase::ToPlan);

    lifecycleTerminate($cancelled, 'cancelled');
    lifecycleTerminate($terminated, 'terminated');

    expect(lifecyclePhase($cancelledDone))->toBe(WorkOrderWorkPhase::Cancelled)
        ->and(lifecyclePhase($cancelledOpen))->toBe(WorkOrderWorkPhase::Cancelled)
        ->and(lifecyclePhase($toPlan))->toBe(WorkOrderWorkPhase::Terminated)
        ->and($toPlan->work_phase_before_termination)->toBe(WorkOrderWorkPhase::ToPlan)
        ->and(lifecyclePhase($inProgress))->toBe(WorkOrderWorkPhase::Terminated)
        ->and($inProgress->work_phase_before_termination)->toBe(WorkOrderWorkPhase::InProgress)
        ->and(lifecyclePhase($done))->toBe(WorkOrderWorkPhase::Completed)
        ->and(lifecyclePhase($elsewhere))->toBe(WorkOrderWorkPhase::ToPlan);
});

it('AC-004: reopening a terminated contract restores the phase each commessa held', function () {
    lifecycleContractActor();
    $contract = Contract::factory()->create();
    $toPlan = lifecycleWorkOrder(WorkOrderWorkPhase::ToPlan, ['quote_id' => $contract->quote_id]);
    $inProgress = lifecycleWorkOrder(WorkOrderWorkPhase::InProgress, ['quote_id' => $contract->quote_id]);
    $migrated = lifecycleWorkOrder(WorkOrderWorkPhase::Terminated, ['quote_id' => $contract->quote_id]);

    lifecycleTerminate($contract, 'terminated');
    $this->postJson("/api/contracts/{$contract->id}/reactivate", [
        'contract_status_id' => ContractStatus::where('system_key', 'new')->sole()->id,
    ])->assertOk();

    expect(lifecyclePhase($toPlan))->toBe(WorkOrderWorkPhase::ToPlan)
        ->and(lifecyclePhase($inProgress))->toBe(WorkOrderWorkPhase::InProgress)
        ->and($inProgress->work_phase_before_termination)->toBeNull()
        ->and(lifecyclePhase($migrated))->toBe(WorkOrderWorkPhase::ToPlan);
});
