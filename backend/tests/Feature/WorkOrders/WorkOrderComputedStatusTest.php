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
 * Records the cumulative $steps ([percentage => 'Y-m-d']) on $workOrder.
 *
 * @param  array<int, string>  $steps
 */
function recordProgress(WorkOrder $workOrder, array $steps): WorkOrder
{
    foreach ($steps as $percentage => $date) {
        WorkOrderProgressEntry::factory()->forWorkOrder($workOrder)->reached($percentage, $date)->create();
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

it('without entries a commessa is open at 0% with no date, even with completed tasks (AC-001)', function () {
    $workOrder = WorkOrder::factory()->create(['title' => 'bare']);
    $done = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->completion(100)->create();
    Task::factory()->inStatus($done)->create(['work_order_id' => $workOrder->id]);

    $detail = workOrderDetail($this, $workOrder);
    $row = collect(workOrderRows($this))->firstWhere('id', $workOrder->id);

    expect($detail['status']['value'])->toBe('open')
        ->and($detail['completion_percentage'])->toBe(0)
        ->and($detail['completion_recorded_on'])->toBeNull()
        ->and($row['status'])->toBe('open')
        ->and($row['completion_percentage'])->toBe(0)
        ->and($row['completion_recorded_on'])->toBeNull();
});

it('the percentage and date are those of the highest entry, in detail and grid (AC-002)', function () {
    $workOrder = recordProgress(WorkOrder::factory()->create(), [30 => '2026-10-01', 60 => '2026-10-10']);

    $detail = workOrderDetail($this, $workOrder);
    $row = collect(workOrderRows($this))->firstWhere('id', $workOrder->id);

    expect($detail['status']['value'])->toBe('in_progress')
        ->and($detail['completion_percentage'])->toBe(60)
        ->and($detail['completion_recorded_on'])->toBe('2026-10-10')
        ->and($row['status'])->toBe('in_progress')
        ->and($row['completion_percentage'])->toBe(60)
        ->and($row['completion_recorded_on'])->toBe('2026-10-10');
});

it('an entry at 100 completes the commessa, force-close wins whatever the percentage (AC-003)', function () {
    $completed = recordProgress(WorkOrder::factory()->create(), [40 => '2026-10-01', 100 => '2026-10-20']);
    $closed = recordProgress(WorkOrder::factory()->forceClosed()->create(), [40 => '2026-10-01']);

    $closedDetail = workOrderDetail($this, $closed);

    expect(workOrderDetail($this, $completed)['status']['value'])->toBe('completed')
        ->and(workOrderDetail($this, $completed)['completion_percentage'])->toBe(100)
        ->and($closedDetail['status']['value'])->toBe('closed')
        ->and($closedDetail['completion_percentage'])->toBe(40);
});

it('the status filter returns exactly the rows badged with the requested values (AC-004)', function () {
    WorkOrder::factory()->create(['title' => 'empty']);
    recordProgress(WorkOrder::factory()->create(['title' => 'in_progress']), [1 => '2026-10-01']);
    recordProgress(WorkOrder::factory()->create(['title' => 'almost']), [99 => '2026-10-01']);
    recordProgress(WorkOrder::factory()->create(['title' => 'completed']), [50 => '2026-10-01', 100 => '2026-10-02']);
    recordProgress(WorkOrder::factory()->forceClosed()->create(['title' => 'closed']), [100 => '2026-10-01']);
    WorkOrder::factory()->forceClosed()->create(['title' => 'closed_empty']);

    $filtered = fn (array $values): array => collect(workOrderRows($this, [
        'filterModel' => ['status' => ['filterType' => 'set', 'values' => $values]],
    ]))->pluck('title')->sort()->values()->all();

    expect($filtered(['open']))->toBe(['empty'])
        ->and($filtered(['in_progress']))->toBe(['almost', 'in_progress'])
        ->and($filtered(['completed']))->toBe(['completed'])
        ->and($filtered(['closed']))->toBe(['closed', 'closed_empty'])
        ->and($filtered(['open', 'completed']))->toBe(['completed', 'empty']);

    foreach (workOrderRows($this) as $row) {
        $detail = $this->getJson("/api/work-orders/{$row['id']}")->json('data');
        expect($row['status'])->toBe($detail['status']['value'])
            ->and($row['completion_percentage'])->toBe($detail['completion_percentage'])
            ->and($row['completion_recorded_on'])->toBe($detail['completion_recorded_on']);
    }
});

it('sorts rows by the manual percentage, a commessa without entries at 0 (AC-005)', function () {
    recordProgress(WorkOrder::factory()->create(['title' => 'half']), [20 => '2026-10-01', 50 => '2026-10-02']);
    recordProgress(WorkOrder::factory()->create(['title' => 'full']), [100 => '2026-10-01']);
    WorkOrder::factory()->create(['title' => 'none']);

    $sorted = fn (string $direction): array => collect(workOrderRows($this, [
        'sortModel' => [['colId' => 'completion_percentage', 'sort' => $direction]],
    ]))->pluck('title')->all();

    expect($sorted('asc'))->toBe(['none', 'half', 'full'])
        ->and($sorted('desc'))->toBe(['full', 'half', 'none']);
});

it('deleting the top entry falls back to the previous one', function () {
    $workOrder = recordProgress(WorkOrder::factory()->create(), [30 => '2026-10-01', 60 => '2026-10-10']);
    WorkOrderProgressEntry::query()->where('work_order_id', $workOrder->id)->where('percentage', 60)->delete();

    $detail = workOrderDetail($this, $workOrder);

    expect($detail['completion_percentage'])->toBe(30)
        ->and($detail['completion_recorded_on'])->toBe('2026-10-01');
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

    recordProgress(WorkOrder::factory()->create(), [30 => '2026-10-01']);
    // Warm-up: the first request also fills the permission cache.
    workOrderRows($this);
    $baseline = $countQueries();

    foreach (range(1, 5) as $ignored) {
        recordProgress(WorkOrder::factory()->create(), [30 => '2026-10-01', 60 => '2026-10-02']);
    }

    expect($countQueries())->toBe($baseline);
});
