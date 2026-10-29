<?php

use App\Models\MigrationRun;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\Task;
use App\Models\WorkOrder;
use App\Models\WorkOrderProgressEntry;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// Task import carries the working status snapshot and triggers nothing (spec 0323, D-9/D-10). AC-018.

beforeEach(function () {
    seedMigrationsConfig();
    legacyTaskCreator();
});

function workOrderForStatusImport(): WorkOrder
{
    $registry = Registry::factory()->create(['old_id' => 700]);
    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    $workOrder->forceFill(['old_id' => 17])->save();

    return $workOrder;
}

it('AC-018: the task gets the status resolved by old_id and the work order is untouched', function () {
    $status = WorkOrderWorkflowStatus::factory()->create(['old_id' => 305, 'progress_percentage' => 60]);
    $workOrder = workOrderForStatusImport();
    $statusBefore = $workOrder->work_order_workflow_status_id;

    $run = runLegacyTasksImport([legacyTaskRecord([
        'work_order_id' => 17,
        'work_order_workflow_status_old_id' => 305,
    ])]);

    $task = Task::query()->where('old_id', 500)->firstOrFail();
    expect($task->work_order_workflow_status_id)->toBe($status->id)
        ->and(legacyTaskErrors($run))->toBe([])
        ->and($workOrder->fresh()->work_order_workflow_status_id)->toBe($statusBefore)
        ->and(WorkOrderProgressEntry::query()->count())->toBe(0);
});

it('AC-018: an old_id not migrated leaves the task unlinked with a warning', function () {
    $run = runLegacyTasksImport([legacyTaskRecord(['work_order_workflow_status_old_id' => 999])]);

    expect(Task::query()->where('old_id', 500)->firstOrFail()->work_order_workflow_status_id)->toBeNull()
        ->and(implode(' ', legacyTaskWarnings($run)))->toContain('old_id 999');
});

it('AC-018: a task without a status stays unlinked and raises no warning', function () {
    $run = runLegacyTasksImport([legacyTaskRecord(['work_order_workflow_status_old_id' => null])]);

    expect(Task::query()->where('old_id', 500)->firstOrFail()->work_order_workflow_status_id)->toBeNull()
        ->and(legacyTaskWarnings($run))->toBe([]);
});

// Spec 0323 D-12: a re-run fills the snapshot of a task imported before the link existed.

function reimportTaskWithStatus(?int $statusOldId): MigrationRun
{
    Http::swap(new Factory);

    return runLegacyTasksImport([legacyTaskRecord(['work_order_workflow_status_old_id' => $statusOldId])]);
}

it('D-12: a re-run links a task already imported without status and leaves the work order untouched', function () {
    $status = WorkOrderWorkflowStatus::factory()->create(['old_id' => 305, 'progress_percentage' => 60]);
    $workOrder = workOrderForStatusImport();
    runLegacyTasksImport([legacyTaskRecord(['work_order_id' => 17, 'work_order_workflow_status_old_id' => null])]);
    $statusBefore = $workOrder->fresh()->work_order_workflow_status_id;

    Http::swap(new Factory);
    $run = runLegacyTasksImport([legacyTaskRecord(['work_order_id' => 17, 'work_order_workflow_status_old_id' => 305])]);

    expect(Task::query()->where('old_id', 500)->count())->toBe(1)
        ->and(Task::query()->where('old_id', 500)->firstOrFail()->work_order_workflow_status_id)->toBe($status->id)
        ->and($run->created_rows)->toBe(1)
        ->and($workOrder->fresh()->work_order_workflow_status_id)->toBe($statusBefore)
        ->and(WorkOrderProgressEntry::query()->count())->toBe(0);
});

it('D-12: a status already on the task is never overwritten', function () {
    $first = WorkOrderWorkflowStatus::factory()->create(['old_id' => 305]);
    WorkOrderWorkflowStatus::factory()->create(['old_id' => 306]);
    runLegacyTasksImport([legacyTaskRecord(['work_order_workflow_status_old_id' => 305])]);

    $run = reimportTaskWithStatus(306);

    expect(Task::query()->where('old_id', 500)->firstOrFail()->work_order_workflow_status_id)->toBe($first->id)
        ->and($run->skipped_rows)->toBe(1);
});

it('D-12: a re-run without a legacy status is skipped', function () {
    runLegacyTasksImport([legacyTaskRecord(['work_order_workflow_status_old_id' => null])]);

    $run = reimportTaskWithStatus(null);

    expect(Task::query()->where('old_id', 500)->firstOrFail()->work_order_workflow_status_id)->toBeNull()
        ->and($run->skipped_rows)->toBe(1)
        ->and($run->created_rows)->toBe(0);
});
