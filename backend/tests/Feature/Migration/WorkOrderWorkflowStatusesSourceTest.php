<?php

use App\Models\MigrationRun;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflow;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

/**
 * `work-order-workflow-statuses` source (spec 0322, D-12): AC-018. Fixtures mirror the real legacy shape.
 */
uses(RefreshDatabase::class);

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function runLegacyWorkOrderWorkflowStatusesImport(array $records): MigrationRun
{
    Http::fake([
        fakeMigrationsBaseUrl().'/work-order-workflow-statuses*' => Http::response([
            'items' => $records,
            'pagination' => ['total' => count($records)],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'work-order-workflow-statuses']);
    runMigrationJobFor($run);

    return $run->fresh();
}

function statusWorkOrder(int $legacyId): WorkOrder
{
    $workOrder = WorkOrder::factory()->create();
    $workOrder->forceFill(['old_id' => $legacyId])->save();

    return $workOrder;
}

function legacyWorkflowStatus(int $legacyId, bool $global): WorkOrderWorkflowStatus
{
    $status = WorkOrderWorkflowStatus::factory()->create([
        'work_order_workflow_id' => $global ? null : WorkOrderWorkflow::factory()->create()->id,
    ]);
    $status->forceFill(['old_id' => $legacyId])->save();

    return $status;
}

beforeEach(fn () => seedMigrationsConfig());

it('AC-018: writes a status of the resolved set without activity log', function () {
    $workOrder = statusWorkOrder(17);
    $status = legacyWorkflowStatus(10, global: true);
    Activity::query()->delete();

    $run = runLegacyWorkOrderWorkflowStatusesImport([['id' => 17, 'status_id' => 10, 'warnings' => []]]);

    expect($run->created_rows)->toBe(1)->and($run->failed_rows)->toBe(0)
        ->and($workOrder->fresh()->work_order_workflow_status_id)->toBe($status->id)
        ->and(Activity::query()->where('subject_type', (new WorkOrder)->getMorphClass())->count())->toBe(0);
});

it('AC-018: a re-run changes nothing', function () {
    statusWorkOrder(17);
    legacyWorkflowStatus(10, global: true);
    $records = [['id' => 17, 'status_id' => 10, 'warnings' => []]];

    runLegacyWorkOrderWorkflowStatusesImport($records);
    $second = runLegacyWorkOrderWorkflowStatusesImport($records);

    expect($second->skipped_rows)->toBe(1)->and($second->created_rows)->toBe(0);
});

it('AC-018: a status outside the resolved set leaves the work order unchanged and warns', function () {
    $workOrder = statusWorkOrder(17);
    $before = $workOrder->work_order_workflow_status_id;
    legacyWorkflowStatus(10, global: false);

    $run = runLegacyWorkOrderWorkflowStatusesImport([['id' => 17, 'status_id' => 10, 'warnings' => []]]);

    expect($run->failed_rows)->toBe(0)
        ->and($workOrder->fresh()->work_order_workflow_status_id)->toBe($before)
        ->and(collect($run->report)->where('level', 'warning')->count())->toBe(1);
});

it('a force-closed work order keeps its closing status and warns', function () {
    $workOrder = statusWorkOrder(17);
    $workOrder->forceFill([
        'is_force_closed' => true,
        'force_close_reason' => 'Legacy closed',
        'work_order_workflow_status_id' => globalWorkOrderStatus('closed')->id,
    ])->save();
    legacyWorkflowStatus(10, global: true);

    $run = runLegacyWorkOrderWorkflowStatusesImport([['id' => 17, 'status_id' => 10, 'warnings' => []]]);

    expect($run->failed_rows)->toBe(0)->and($run->created_rows)->toBe(0)
        ->and($workOrder->fresh()->work_order_workflow_status_id)->toBe(globalWorkOrderStatus('closed')->id)
        ->and(collect($run->report)->where('level', 'warning')->count())->toBe(1);
});

it('AC-018: a work order not migrated fails the row', function () {
    legacyWorkflowStatus(10, global: true);

    $run = runLegacyWorkOrderWorkflowStatusesImport([['id' => 99, 'status_id' => 10, 'warnings' => []]]);

    expect($run->failed_rows)->toBe(1)->and($run->created_rows)->toBe(0);
});

it('AC-018: a status not migrated fails the row', function () {
    $workOrder = statusWorkOrder(17);
    $before = $workOrder->work_order_workflow_status_id;

    $run = runLegacyWorkOrderWorkflowStatusesImport([['id' => 17, 'status_id' => 555, 'warnings' => []]]);

    expect($run->failed_rows)->toBe(1)->and($workOrder->fresh()->work_order_workflow_status_id)->toBe($before);
});

it('AC-018: an orphan row fails with the legacy reason', function () {
    $run = runLegacyWorkOrderWorkflowStatusesImport([
        ['id' => 'orderisos:90', 'orphan_reason' => 'Order 90 does not exist.', 'warnings' => []],
    ]);

    expect($run->failed_rows)->toBe(1)
        ->and(collect($run->report)->first()['message'])->toContain('Order 90 does not exist.');
});
