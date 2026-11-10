<?php

use App\Migrations\Realign\LegacyRealignReport;
use App\Migrations\Realign\LegacyRealignRunner;
use App\Migrations\Realign\Targets\WorkOrderWorkflowStatusesTarget;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

// Spec 0375 AC-011: the working status of a commessa, on its own source (spec 0372 D-13).

function realignWorkOrderStatus(bool $apply): LegacyRealignReport
{
    fakeLegacyRealign('work-order-workflow-statuses', [17], [], [['id' => 17, 'status_id' => 10, 'warnings' => []]]);
    $report = new LegacyRealignReport;
    app(LegacyRealignRunner::class)->run(app(WorkOrderWorkflowStatusesTarget::class), '2026-10-23', $apply, $report);

    return $report;
}

beforeEach(function () {
    seedMigrationsConfig();
    $this->workOrder = WorkOrder::factory()->create();
    $this->workOrder->forceFill(['old_id' => 17])->saveQuietly();
    $this->status = WorkOrderWorkflowStatus::factory()->create(['name' => 'In lavorazione', 'work_order_workflow_id' => null]);
    $this->status->forceFill(['old_id' => 10])->saveQuietly();
    Activity::query()->delete();
});

it('AC-011: moves the commessa to the legacy status and reports it by name', function () {
    $report = realignWorkOrderStatus(apply: true);

    expect($this->workOrder->fresh()->work_order_workflow_status_id)->toBe($this->status->id)
        ->and($report->changes[0][4])->toBe('status')
        ->and($report->changes[0][6])->toBe('In lavorazione')
        ->and(Activity::query()->where('subject_type', (new WorkOrder)->getMorphClass())->count())->toBe(0);
});

it('AC-011: a simulation leaves the status', function () {
    $before = $this->workOrder->work_order_workflow_status_id;

    $report = realignWorkOrderStatus(apply: false);

    expect($this->workOrder->fresh()->work_order_workflow_status_id)->toBe($before)
        ->and($report->counters['work-order-workflow-statuses'][LegacyRealignReport::ALIGNED])->toBe(1);
});

it('AC-005: a stage changed on qnet-2 protects the status of the commessa', function () {
    $before = $this->workOrder->work_order_workflow_status_id;
    $stage = WorkOrderStage::factory()->create(['work_order_id' => $this->workOrder->id]);
    activity()->performedOn($stage)->event('updated')->log('updated');

    $report = realignWorkOrderStatus(apply: true);

    expect($this->workOrder->fresh()->work_order_workflow_status_id)->toBe($before)
        ->and($report->counters['work-order-workflow-statuses'][LegacyRealignReport::SKIPPED_TOUCHED])->toBe(1);
});
