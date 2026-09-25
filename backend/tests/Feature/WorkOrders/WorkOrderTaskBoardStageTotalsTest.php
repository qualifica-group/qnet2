<?php

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Per-fase segnatempo totals on GET /api/work-orders/{workOrder}/task-board
| (spec 0163, D-4/AC-007)
|--------------------------------------------------------------------------
|
| `workOrderUserWith()` is declared (guarded) in WorkOrderCrudTest.php, in
| this same directory — reused here rather than redeclared, the shared-Pest-
| helper idiom this repo already uses (see TaskBoardEndpointsTest.php).
*/

if (! function_exists('stageTotalsBoardActor')) {
    function stageTotalsBoardActor(): User
    {
        $actor = workOrderUserWith(['view']);

        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }
        $actor->givePermissionTo('tasks.viewAny');

        return $actor;
    }
}

it('AC-007: each stage carries its own logged_minutes, and the commessa carries unstaged_logged_minutes', function () {
    $actor = stageTotalsBoardActor();
    $workOrder = WorkOrder::factory()->create();
    $stageA = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $stageB = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $otherWorkOrder = WorkOrder::factory()->create();
    $otherStage = WorkOrderStage::factory()->forWorkOrder($otherWorkOrder)->create();

    TimeEntry::factory()->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id, 'minutes' => 30]);
    TimeEntry::factory()->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id, 'minutes' => 45]);
    TimeEntry::factory()->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageB->id, 'minutes' => 20]);
    TimeEntry::factory()->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => null, 'minutes' => 15]);
    TimeEntry::factory()->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => null, 'minutes' => 5]);
    // Noise: another commessa's own voci must never leak into these totals.
    TimeEntry::factory()->create(['work_order_id' => $otherWorkOrder->id, 'work_order_stage_id' => $otherStage->id, 'minutes' => 1000]);
    TimeEntry::factory()->create(['work_order_id' => $otherWorkOrder->id, 'work_order_stage_id' => null, 'minutes' => 1000]);

    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/work-orders/{$workOrder->id}/task-board")->assertOk();

    $stages = collect($response->json('data.stages'))->keyBy('id');

    expect($stages[$stageA->id]['logged_minutes'])->toBe(75)
        ->and($stages[$stageB->id]['logged_minutes'])->toBe(20)
        ->and($response->json('data.unstaged_logged_minutes'))->toBe(20);
});

it('AC-007: a stage with no voci logged shows 0, never null or a missing key', function () {
    $actor = stageTotalsBoardActor();
    $workOrder = WorkOrder::factory()->create();
    $emptyStage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/work-orders/{$workOrder->id}/task-board")->assertOk();

    $stages = collect($response->json('data.stages'))->keyBy('id');

    expect($stages[$emptyStage->id]['logged_minutes'])->toBe(0)
        ->and($response->json('data.unstaged_logged_minutes'))->toBe(0);
});

it('AC-007: totals never N+1 (bounded query count regardless of stage/task count)', function () {
    $actor = stageTotalsBoardActor();
    $workOrder = WorkOrder::factory()->create();

    foreach (range(1, 5) as $i) {
        $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
        TimeEntry::factory()->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stage->id]);
        Task::factory()->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stage->id]);
    }

    Sanctum::actingAs($actor);

    DB::enableQueryLog();
    $this->getJson("/api/work-orders/{$workOrder->id}/task-board")->assertOk();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThan(20);
});
