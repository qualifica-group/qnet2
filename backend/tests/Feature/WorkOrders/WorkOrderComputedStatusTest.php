<?php

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
 * Spec 0149: the commessa's status (open / in_progress / completed / closed)
 * and completion percentage, computed from its ROOT tasks.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['viewAny', 'view', 'viewAll'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }

    $this->actor = User::factory()->create();
    $this->actor->givePermissionTo(['work-orders.viewAny', 'work-orders.view', 'work-orders.viewAll']);
    Sanctum::actingAs($this->actor);

    $this->statuses = [
        'open' => TaskStatus::factory()->group(TaskStatusGroup::Open)->completion(0)->create(),
        'pending' => TaskStatus::factory()->group(TaskStatusGroup::Pending)->completion(50)->create(),
        'done' => TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->completion(100)->create(),
        'cancelled' => TaskStatus::factory()->group(TaskStatusGroup::ClosedNegative)->completion(0)->create(),
    ];
});

/**
 * Creates one ROOT task per entry of $statusKeys on $workOrder.
 *
 * @param  array<int, string>  $statusKeys
 */
function rootTasksIn(WorkOrder $workOrder, array $statusKeys, array $statuses): void
{
    foreach ($statusKeys as $key) {
        Task::factory()->inStatus($statuses[$key])->create(['work_order_id' => $workOrder->id]);
    }
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

it('a commessa without tasks is open at 0% (AC-001)', function () {
    $workOrder = WorkOrder::factory()->create();

    $detail = workOrderDetail($this, $workOrder);

    expect($detail['status']['value'])->toBe('open')
        ->and($detail['completion_percentage'])->toBe(0);
});

it('is open while no root task is completed (AC-002)', function () {
    $workOrder = WorkOrder::factory()->create();
    rootTasksIn($workOrder, ['open', 'pending'], $this->statuses);

    expect(workOrderDetail($this, $workOrder)['status']['value'])->toBe('open');
});

it('is in progress with one completed root task and one still open (AC-003)', function () {
    $workOrder = WorkOrder::factory()->create();
    rootTasksIn($workOrder, ['done', 'pending'], $this->statuses);

    expect(workOrderDetail($this, $workOrder)['status']['value'])->toBe('in_progress');
});

it('is completed when every root task is completed (AC-004)', function () {
    $workOrder = WorkOrder::factory()->create();
    rootTasksIn($workOrder, ['done', 'done'], $this->statuses);

    expect(workOrderDetail($this, $workOrder)['status']['value'])->toBe('completed');
});

it('ignores cancelled root tasks: completed + cancelled is completed, all cancelled is open (AC-005)', function () {
    $mixed = WorkOrder::factory()->create();
    rootTasksIn($mixed, ['done', 'cancelled'], $this->statuses);
    $allCancelled = WorkOrder::factory()->create();
    rootTasksIn($allCancelled, ['cancelled', 'cancelled'], $this->statuses);

    expect(workOrderDetail($this, $mixed)['status']['value'])->toBe('completed')
        ->and(workOrderDetail($this, $mixed)['completion_percentage'])->toBe(100)
        ->and(workOrderDetail($this, $allCancelled)['status']['value'])->toBe('open')
        ->and(workOrderDetail($this, $allCancelled)['completion_percentage'])->toBe(0);
});

it('a force-closed commessa is closed whatever its tasks, keeping its real percentage (AC-006)', function () {
    $workOrder = WorkOrder::factory()->forceClosed()->create();
    rootTasksIn($workOrder, ['done', 'pending'], $this->statuses);

    $detail = workOrderDetail($this, $workOrder);

    expect($detail['status']['value'])->toBe('closed')
        ->and($detail['completion_percentage'])->toBe(75);
});

it('sub-tasks do not count (AC-007)', function () {
    $workOrder = WorkOrder::factory()->create();
    $root = Task::factory()->inStatus($this->statuses['open'])->create(['work_order_id' => $workOrder->id]);
    Task::factory()->inStatus($this->statuses['done'])->childOf($root)->create(['work_order_id' => $workOrder->id]);

    $detail = workOrderDetail($this, $workOrder);

    expect($detail['status']['value'])->toBe('open')
        ->and($detail['completion_percentage'])->toBe(0);
});

it('counts every root task, including the ones the actor cannot see (AC-008)', function () {
    $workOrder = WorkOrder::factory()->create();
    // Created by someone else, no assignee: TaskVisibilityScope hides both from the actor.
    rootTasksIn($workOrder, ['done', 'done'], $this->statuses);

    $detail = workOrderDetail($this, $workOrder);
    $row = collect(workOrderRows($this))->firstWhere('id', $workOrder->id);

    expect($detail['status']['value'])->toBe('completed')
        ->and($row['status'])->toBe('completed')
        ->and($row['completion_percentage'])->toBe(100);
});

it('the percentage is the rounded mean of the non-cancelled root tasks (AC-009)', function () {
    $workOrder = WorkOrder::factory()->create();
    $third = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->completion(33)->create();
    rootTasksIn($workOrder, ['done', 'pending', 'cancelled'], $this->statuses);
    Task::factory()->inStatus($third)->create(['work_order_id' => $workOrder->id]);

    // (100 + 50 + 33) / 3 = 61
    expect(workOrderDetail($this, $workOrder)['completion_percentage'])->toBe(61);
});

it('the status filter returns exactly the rows badged with the requested values (AC-010)', function () {
    $open = WorkOrder::factory()->create(['title' => 'open']);
    rootTasksIn($open, ['open'], $this->statuses);
    $inProgress = WorkOrder::factory()->create(['title' => 'in_progress']);
    rootTasksIn($inProgress, ['done', 'open'], $this->statuses);
    $completed = WorkOrder::factory()->create(['title' => 'completed']);
    rootTasksIn($completed, ['done', 'cancelled'], $this->statuses);
    $closed = WorkOrder::factory()->forceClosed()->create(['title' => 'closed']);
    rootTasksIn($closed, ['done'], $this->statuses);
    WorkOrder::factory()->create(['title' => 'empty']);

    $filtered = fn (array $values): array => collect(workOrderRows($this, [
        'filterModel' => ['status' => ['filterType' => 'set', 'values' => $values]],
    ]))->pluck('title')->sort()->values()->all();

    expect($filtered(['open']))->toBe(['empty', 'open'])
        ->and($filtered(['in_progress']))->toBe(['in_progress'])
        ->and($filtered(['completed']))->toBe(['completed'])
        ->and($filtered(['closed']))->toBe(['closed'])
        ->and($filtered(['in_progress', 'closed']))->toBe(['closed', 'in_progress']);

    foreach (workOrderRows($this) as $row) {
        $detail = $this->getJson("/api/work-orders/{$row['id']}")->json('data');
        expect($row['status'])->toBe($detail['status']['value'])
            ->and($row['completion_percentage'])->toBe($detail['completion_percentage']);
    }
});

it('sorts rows by completion percentage, a commessa without tasks at 0 (AC-012)', function () {
    $half = WorkOrder::factory()->create(['title' => 'half']);
    rootTasksIn($half, ['done', 'open'], $this->statuses);
    $full = WorkOrder::factory()->create(['title' => 'full']);
    rootTasksIn($full, ['done'], $this->statuses);
    WorkOrder::factory()->create(['title' => 'none']);

    $sorted = fn (string $direction): array => collect(workOrderRows($this, [
        'sortModel' => [['colId' => 'completion_percentage', 'sort' => $direction]],
    ]))->pluck('title')->all();

    expect($sorted('asc'))->toBe(['none', 'half', 'full'])
        ->and($sorted('desc'))->toBe(['full', 'half', 'none']);
});

it('the rows query count does not grow with the number of work orders (AC-013)', function () {
    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        workOrderRows($this);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $workOrder = WorkOrder::factory()->create();
    rootTasksIn($workOrder, ['done', 'open'], $this->statuses);
    // Warm-up: the first request also fills the permission cache.
    workOrderRows($this);
    $baseline = $countQueries();

    foreach (range(1, 5) as $ignored) {
        rootTasksIn(WorkOrder::factory()->create(), ['done', 'open'], $this->statuses);
    }

    expect($countQueries())->toBe($baseline);
});
