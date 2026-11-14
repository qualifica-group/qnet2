<?php

declare(strict_types=1);

use App\Enums\WorkOrderWorkPhase;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0395 (REQUIREMENT CHANGED, was the 2026-11-09 derivation from "Stato
// lavorazione" and the contract): the `status` column and the tabs read the persisted legacy phase.

uses(RefreshDatabase::class);

beforeEach(function () {
    $actor = User::factory()->create();

    foreach (['viewAny', 'viewAll'] as $ability) {
        $actor->givePermissionTo(Permission::findOrCreate("work-orders.{$ability}"));
    }

    Sanctum::actingAs($actor);
});

function phaseWorkOrder(string $phase): WorkOrder
{
    $workOrder = WorkOrder::factory()->create();
    $workOrder->forceFill(['work_phase' => WorkOrderWorkPhase::from($phase)])->save();

    return $workOrder;
}

/**
 * @param  array<int, string>  $phases
 * @return array<int, array<string, mixed>>
 */
function phaseRows(array $phases): array
{
    return test()->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0,
        'endRow' => 50,
        'filterModel' => ['status' => ['filterType' => 'set', 'values' => $phases]],
    ])->assertOk()->json('items');
}

it('each tab lists exactly its own commesse and labels them', function (string $phase) {
    $byPhase = collect(WorkOrderWorkPhase::values())->mapWithKeys(fn (string $value) => [$value => phaseWorkOrder($value)]);

    $rows = phaseRows([$phase]);

    expect(collect($rows)->pluck('id')->all())->toBe([$byPhase[$phase]->id])
        ->and($rows[0]['status'])->toBe($phase);
})->with(WorkOrderWorkPhase::values());

it('the phase does not follow "Stato lavorazione" nor the contract, as in the legacy', function () {
    $workOrder = phaseWorkOrder('in_progress');
    $workOrder->forceFill(['work_order_workflow_status_id' => globalWorkOrderStatus('open')->id])->save();

    expect(collect(phaseRows(['in_progress']))->pluck('id')->all())->toBe([$workOrder->id])
        ->and(phaseRows(['to_plan']))->toBeEmpty();
});

it('the status column is the phase: visible, set-filtered over the five phases', function () {
    $column = collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.columns'))->firstWhere('id', 'status');

    expect($column['visible'])->toBeTrue()->and($column['filterType'])->toBe('set');

    $values = $this->postJson('/api/tables/work-orders/values', ['columnId' => 'status', 'limit' => 50])->assertOk()->json('data.values');

    expect($values)->toEqualCanonicalizing(['to_plan', 'in_progress', 'completed', 'cancelled', 'terminated']);
});

it('ignores an unknown phase rather than failing', function () {
    phaseWorkOrder('to_plan');

    expect(phaseRows(['nope']))->toHaveCount(1);
});

/**
 * @param  array<string, mixed>  $state
 * @return array<string, mixed>
 */
function phaseResponse(array $state = []): array
{
    return test()->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0,
        'endRow' => 50,
        'work_order_type' => 'processing',
        ...$state,
    ])->assertOk()->json();
}

/** @param  array<int, string>  $phases */
function phaseFilter(array $phases): array
{
    return ['filterModel' => ['status' => ['filterType' => 'set', 'values' => $phases]]];
}

function phaseProcessingWorkOrder(string $phase, string $title = 'Plain'): WorkOrder
{
    $workOrder = phaseWorkOrder($phase);
    $workOrder->forceFill(['type' => 'processing', 'title' => $title])->save();

    return $workOrder;
}

it('counts every phase, and the active tab does not change the figures', function () {
    phaseProcessingWorkOrder('to_plan');
    phaseProcessingWorkOrder('to_plan');
    phaseProcessingWorkOrder('in_progress');
    phaseProcessingWorkOrder('completed');
    phaseProcessingWorkOrder('cancelled');
    phaseProcessingWorkOrder('cancelled');
    phaseProcessingWorkOrder('terminated');

    $expected = ['all' => 7, 'to_plan' => 2, 'in_progress' => 1, 'completed' => 1, 'cancelled' => 2, 'terminated' => 1];

    expect(phaseResponse()['meta']['aggregates'])->toEqual($expected)
        ->and(phaseResponse(phaseFilter(['to_plan']))['meta']['aggregates'])->toEqual($expected);
});

it('each count equals the rows its tab returns, under the same search and column filters', function () {
    phaseProcessingWorkOrder('to_plan', 'Alpha one');
    phaseProcessingWorkOrder('to_plan', 'Beta one');
    phaseProcessingWorkOrder('terminated', 'Alpha two');
    phaseProcessingWorkOrder('cancelled', 'Beta two');

    $state = ['filterModel' => ['title' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'Alpha']]];
    $counts = phaseResponse($state)['meta']['aggregates'];

    expect($counts['all'])->toBe(2);

    foreach (['to_plan', 'in_progress', 'completed', 'cancelled', 'terminated'] as $phase) {
        $tabState = $state;
        $tabState['filterModel']['status'] = ['filterType' => 'set', 'values' => [$phase]];

        expect(phaseResponse($tabState)['items'])->toHaveCount($counts[$phase]);
    }
});

it('counts only the commesse the actor can see', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(Permission::findOrCreate('work-orders.viewAny', 'web'));
    Sanctum::actingAs($actor);

    $own = phaseProcessingWorkOrder('to_plan');
    $own->supervisors()->attach($actor->id);
    phaseProcessingWorkOrder('to_plan');

    $response = phaseResponse();

    expect($response['meta']['aggregates']['all'])->toBe(count($response['items']));
});

it('does not compute the figures for the other grids of the domain', function () {
    phaseProcessingWorkOrder('to_plan');

    $response = test()->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 50])->assertOk()->json();

    expect($response)->not->toHaveKey('meta.aggregates');
});
