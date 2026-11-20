<?php

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderProgressEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
 * Spec 0321 (supersedes 0149's task rule; REQUIREMENT CHANGED, D-1/D-2): the
 * commessa's status (open / in_progress / completed / closed) and completion
 * percentage derive from its MANUAL progress entries, tasks no longer count.
 * Revision 2026-10-28 (R-1/R-4): entries are INCREMENTS like the legacy; the
 * percentage is their SUM (may exceed 100), the date their MAX.
 * Spec 0395 (REQUIREMENT CHANGED): the status no longer derives from the
 * percentage; it is the persisted working phase (WorkOrderWorkPhaseTest).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['viewAny', 'view', 'viewAll'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }

    $this->actor = User::factory()->create();
    $this->actor->givePermissionTo(['work-orders.viewAny', 'work-orders.view', 'work-orders.viewAll']);
    Sanctum::actingAs($this->actor);
});

/**
 * Records the $steps (list of [increment, 'Y-m-d']) on $workOrder, in order.
 *
 * @param  list<array{int, string}>  $steps
 */
function recordProgress(WorkOrder $workOrder, array $steps): WorkOrder
{
    foreach ($steps as [$increment, $date]) {
        WorkOrderProgressEntry::factory()->forWorkOrder($workOrder)->increment($increment, $date)->create();
    }

    return $workOrder;
}

function workOrderDetail(object $test, WorkOrder $workOrder): array
{
    return $test->getJson("/api/work-orders/{$workOrder->id}")->assertOk()->json('data');
}

function workOrderRows(object $test, array $payload = []): array
{
    return $test->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 100] + $payload)
        ->assertOk()->json('items');
}

it('without entries a commessa is at 0% with no date, even with completed tasks (AC-001)', function () {
    $workOrder = WorkOrder::factory()->create(['title' => 'bare']);
    $done = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->completion(100)->create();
    Task::factory()->inStatus($done)->create(['work_order_id' => $workOrder->id]);

    $detail = workOrderDetail($this, $workOrder);
    $row = collect(workOrderRows($this))->firstWhere('id', $workOrder->id);

    expect($detail['completion_percentage'])->toBe(0)
        ->and($detail['completion_recorded_on'])->toBeNull()
        ->and($row['completion_percentage'])->toBe(0)
        ->and($row['completion_recorded_on'])->toBeNull();
});

it('the percentage is the SUM of the increments and the date the MAX, in detail and grid (AC-002)', function () {
    $workOrder = recordProgress(WorkOrder::factory()->create(), [[30, '2026-10-01'], [30, '2026-10-10']]);

    $detail = workOrderDetail($this, $workOrder);
    $row = collect(workOrderRows($this))->firstWhere('id', $workOrder->id);

    expect($detail['completion_percentage'])->toBe(60)
        ->and($detail['completion_recorded_on'])->toBe('2026-10-10')
        ->and($row['completion_percentage'])->toBe(60)
        ->and($row['completion_recorded_on'])->toBe('2026-10-10');
});

it('the percentage does not move the status: 100% leaves the phase alone (spec 0395)', function () {
    $completed = recordProgress(WorkOrder::factory()->create(), [[40, '2026-10-01'], [60, '2026-10-20']]);

    $detail = workOrderDetail($this, $completed);
    $row = collect(workOrderRows($this))->firstWhere('id', $completed->id);

    expect($detail['completion_percentage'])->toBe(100)
        ->and($detail['status']['value'])->toBe('to_plan')
        ->and($row['status'])->toBe('to_plan')
        ->and($row['completion_percentage'])->toBe($detail['completion_percentage'])
        ->and($row['completion_recorded_on'])->toBe($detail['completion_recorded_on']);
});

it('sorts rows by the manual percentage, a commessa without entries at 0 (AC-005)', function () {
    recordProgress(WorkOrder::factory()->create(['title' => 'half']), [[20, '2026-10-01'], [30, '2026-10-02']]);
    recordProgress(WorkOrder::factory()->create(['title' => 'full']), [[100, '2026-10-01']]);
    WorkOrder::factory()->create(['title' => 'none']);

    $sorted = fn (string $direction): array => collect(workOrderRows($this, [
        'sortModel' => [['colId' => 'completion_percentage', 'sort' => $direction]],
    ]))->pluck('title')->all();

    expect($sorted('asc'))->toBe(['none', 'half', 'full'])
        ->and($sorted('desc'))->toBe(['full', 'half', 'none']);
});

it('deleting an entry lowers the total by its increment and the date follows the remaining rows', function () {
    $workOrder = recordProgress(WorkOrder::factory()->create(), [[30, '2026-10-01'], [30, '2026-10-10']]);
    WorkOrderProgressEntry::query()->where('work_order_id', $workOrder->id)->where('recorded_on', '2026-10-10')->delete();

    $detail = workOrderDetail($this, $workOrder);

    expect($detail['completion_percentage'])->toBe(30)
        ->and($detail['completion_recorded_on'])->toBe('2026-10-01');
});

it('zero increments count nothing and legacy sums above 100 are kept (R-4)', function () {
    $zeros = recordProgress(WorkOrder::factory()->create(['title' => 'zeros']), [[0, '2026-10-01'], [0, '2026-10-02']]);
    $over = recordProgress(WorkOrder::factory()->create(['title' => 'over']), [[80, '2026-10-01'], [50, '2026-10-02']]);

    $zerosDetail = workOrderDetail($this, $zeros);
    $overDetail = workOrderDetail($this, $over);

    expect($zerosDetail['completion_percentage'])->toBe(0)
        ->and($zerosDetail['completion_recorded_on'])->toBe('2026-10-02')
        ->and($overDetail['completion_percentage'])->toBe(130);
});

it('the rows query count does not grow with the number of work orders', function () {
    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        workOrderRows($this);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    recordProgress(WorkOrder::factory()->create(), [[30, '2026-10-01']]);
    // Warm-up: the first request also fills the permission cache.
    workOrderRows($this);
    $baseline = $countQueries();

    foreach (range(1, 5) as $ignored) {
        recordProgress(WorkOrder::factory()->create(), [[30, '2026-10-01'], [30, '2026-10-02']]);
    }

    expect($countQueries())->toBe($baseline);
});
