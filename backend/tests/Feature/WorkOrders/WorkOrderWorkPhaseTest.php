<?php

declare(strict_types=1);

use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// User request 2026-11-09: the legacy status tabs of the Commesse page, mapped
// on the groups of "Stato lavorazione" and, for `closed`, on the contract.

uses(RefreshDatabase::class);

beforeEach(function () {
    $actor = User::factory()->create();

    foreach (['viewAny', 'viewAll'] as $ability) {
        $actor->givePermissionTo(Permission::findOrCreate("work-orders.{$ability}"));
    }

    Sanctum::actingAs($actor);
});

function phaseWorkOrder(string $group, ?string $contractSystemKey = null): WorkOrder
{
    $status = WorkOrderWorkflowStatus::query()->whereNull('work_order_workflow_id')->where('group', $group)->first()
        ?? WorkOrderWorkflowStatus::factory()->global()->create(['group' => $group]);

    $workOrder = WorkOrder::factory()->create();
    $workOrder->forceFill(['work_order_workflow_status_id' => $status->id])->save();

    if ($contractSystemKey !== null) {
        Contract::factory()->create([
            'quote_id' => $workOrder->quote_id,
            'contract_status_id' => ContractStatus::where('system_key', $contractSystemKey)->sole()->id,
        ]);
    }

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
        'filterModel' => ['work_phase' => ['filterType' => 'set', 'values' => $phases]],
    ])->assertOk()->json('items');
}

dataset('phases', [
    'to plan' => ['to_plan', 'open', null],
    'in progress' => ['in_progress', 'in_progress', null],
    'completed' => ['completed', 'completed', null],
    'cancelled, no contract' => ['cancelled', 'closed', null],
    'cancelled contract' => ['cancelled', 'closed', 'cancelled'],
    'closed on a working contract' => ['cancelled', 'closed', 'new'],
    'terminated contract' => ['terminated', 'closed', 'terminated'],
]);

it('each tab lists exactly its own commesse and labels them', function (string $phase, string $group, ?string $contractKey) {
    $match = phaseWorkOrder($group, $contractKey);

    foreach ([['open', null], ['in_progress', null], ['completed', null], ['closed', null], ['closed', 'terminated']] as [$otherGroup, $otherKey]) {
        if ($otherGroup !== $group || (($otherKey === 'terminated') !== ($contractKey === 'terminated'))) {
            phaseWorkOrder($otherGroup, $otherKey);
        }
    }

    $rows = phaseRows([$phase]);

    expect(collect($rows)->pluck('id')->all())->toBe([$match->id])
        ->and($rows[0]['work_phase'])->toBe($phase);
})->with('phases');

it('declares the column hidden, with the five phases as filter values', function () {
    $column = collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.columns'))->firstWhere('id', 'work_phase');

    expect($column['visible'])->toBeFalse()->and($column['filterType'])->toBe('set');

    $values = $this->postJson('/api/tables/work-orders/values', ['columnId' => 'work_phase', 'limit' => 50])->assertOk()->json('data.values');

    expect($values)->toEqualCanonicalizing(['to_plan', 'in_progress', 'completed', 'cancelled', 'terminated']);
});

it('ignores an unknown phase rather than failing', function () {
    phaseWorkOrder('open');

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
    return ['filterModel' => ['work_phase' => ['filterType' => 'set', 'values' => $phases]]];
}

function phaseProcessingWorkOrder(string $group, ?string $contractKey = null, string $title = 'Plain'): WorkOrder
{
    $workOrder = phaseWorkOrder($group, $contractKey);
    $workOrder->forceFill(['type' => 'processing', 'title' => $title])->save();

    return $workOrder;
}

it('counts every phase, and the active tab does not change the figures', function () {
    phaseProcessingWorkOrder('open');
    phaseProcessingWorkOrder('open');
    phaseProcessingWorkOrder('in_progress');
    phaseProcessingWorkOrder('completed');
    phaseProcessingWorkOrder('closed');
    phaseProcessingWorkOrder('closed', 'cancelled');
    phaseProcessingWorkOrder('closed', 'terminated');

    $expected = ['all' => 7, 'to_plan' => 2, 'in_progress' => 1, 'completed' => 1, 'cancelled' => 2, 'terminated' => 1];

    expect(phaseResponse()['meta']['aggregates'])->toEqual($expected)
        ->and(phaseResponse(phaseFilter(['to_plan']))['meta']['aggregates'])->toEqual($expected);
});

it('each count equals the rows its tab returns, under the same search and column filters', function () {
    phaseProcessingWorkOrder('open', null, 'Alpha one');
    phaseProcessingWorkOrder('open', null, 'Beta one');
    phaseProcessingWorkOrder('closed', 'terminated', 'Alpha two');
    phaseProcessingWorkOrder('closed', null, 'Beta two');

    $state = ['filterModel' => ['title' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'Alpha']]];
    $counts = phaseResponse($state)['meta']['aggregates'];

    expect($counts['all'])->toBe(2);

    foreach (['to_plan', 'in_progress', 'completed', 'cancelled', 'terminated'] as $phase) {
        $tabState = $state;
        $tabState['filterModel']['work_phase'] = ['filterType' => 'set', 'values' => [$phase]];

        expect(phaseResponse($tabState)['items'])->toHaveCount($counts[$phase]);
    }
});

it('counts only the commesse the actor can see', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(Permission::findOrCreate('work-orders.viewAny', 'web'));
    Sanctum::actingAs($actor);

    $own = phaseProcessingWorkOrder('open');
    $own->supervisors()->attach($actor->id);
    phaseProcessingWorkOrder('open');

    $response = phaseResponse();

    expect($response['meta']['aggregates']['all'])->toBe(count($response['items']));
});

it('does not compute the figures for the other grids of the domain', function () {
    phaseProcessingWorkOrder('open');

    $response = test()->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 50])->assertOk()->json();

    expect($response)->not->toHaveKey('meta.aggregates');
});
