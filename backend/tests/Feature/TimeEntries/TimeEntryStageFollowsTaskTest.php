<?php

use App\Models\Task;
use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A voce's `work_order_stage_id` follows its task's effective fase (spec
| 0167, D-1/D-2, AC-001..AC-010)
|--------------------------------------------------------------------------
|
| Retires spec 0163 AC-004 ("il task cambia fase dopo la registrazione: la
| voce mantiene la fase originale"): that test lived in
| TimeEntryWorkOrderStageTest.php and is REPLACED there by the opposite
| requirement, covered here as AC-001.
*/

if (! function_exists('stageFollowActor')) {
    /**
     * A single actor combining `tasks.*`, `work-orders.*` and
     * `time-entries.*` abilities — every endpoint this suite drives (PATCH
     * task, board move, POST time-entries, GET task-board) in one identity,
     * `viewAll` granted on both scoped resources so a 403 here always means
     * "missing resource permission", the same posture as `taskActorWith`/
     * `boardActor` (TaskCrudTest.php/TaskBoardEndpointsTest.php).
     */
    function stageFollowActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'exportMonthly', 'manageAll', 'viewAll'] as $ability) {
            Permission::findOrCreate("time-entries.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo([
            'tasks.viewAny', 'tasks.view', 'tasks.update', 'tasks.viewAll',
            'work-orders.view', 'work-orders.update', 'work-orders.viewAll',
            'time-entries.create', 'time-entries.view',
        ]);

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-001 — PATCH on a root re-stages the whole tree's voci, other tasks
// untouched
// ---------------------------------------------------------------------------

it('AC-001: PATCH-ing a root task to a new stage realigns its voci, leaving other tasks\' voci untouched', function () {
    $actor = stageFollowActor();
    $secondUser = User::factory()->create();
    $workOrder = WorkOrder::factory()->create();
    $stageA = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $stageB = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();

    $root = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);
    $entryOne = TimeEntry::factory()->forUser($actor)->create(['task_id' => $root->id, 'work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);
    $entryTwo = TimeEntry::factory()->forUser($secondUser)->create(['task_id' => $root->id, 'work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);

    $otherTask = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);
    $otherEntry = TimeEntry::factory()->forUser($actor)->create(['task_id' => $otherTask->id, 'work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$root->id}", ['work_order_stage_id' => $stageB->id])->assertOk();

    $this->assertDatabaseHas('time_entries', ['id' => $entryOne->id, 'work_order_stage_id' => $stageB->id]);
    $this->assertDatabaseHas('time_entries', ['id' => $entryTwo->id, 'work_order_stage_id' => $stageB->id]);
    $this->assertDatabaseHas('time_entries', ['id' => $otherEntry->id, 'work_order_stage_id' => $stageA->id]);
});

// ---------------------------------------------------------------------------
// AC-002 — board drag realigns; a same-stage reorder never touches a voce
// ---------------------------------------------------------------------------

it('AC-002: dragging a root task across stages on the board realigns its voci', function () {
    $actor = stageFollowActor();
    $workOrder = WorkOrder::factory()->create();
    $stageA = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $stageB = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();

    $root = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);
    $entry = TimeEntry::factory()->forUser($actor)->create(['task_id' => $root->id, 'work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);

    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/move", [
        'task_id' => $root->id,
        'work_order_stage_id' => $stageB->id,
        'position' => 0,
    ])->assertOk();

    $this->assertDatabaseHas('time_entries', ['id' => $entry->id, 'work_order_stage_id' => $stageB->id]);
});

it('AC-002: reordering a root task within its own stage never touches its voci', function () {
    $actor = stageFollowActor();
    $workOrder = WorkOrder::factory()->create();
    $stageA = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();

    $moving = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id, 'stage_position' => 0]);
    Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id, 'stage_position' => 1]);
    $entry = TimeEntry::factory()->forUser($actor)->create(['task_id' => $moving->id, 'work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);

    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/move", [
        'task_id' => $moving->id,
        'work_order_stage_id' => $stageA->id,
        'position' => 1,
    ])->assertOk();

    $this->assertDatabaseHas('time_entries', ['id' => $entry->id, 'work_order_stage_id' => $stageA->id]);
});

// ---------------------------------------------------------------------------
// AC-003 — a voce registered on a level-2/level-3 sub-task always takes the
// ROOT's fase, input ignored; a root without fase -> null
// ---------------------------------------------------------------------------

it('AC-003: a voce logged on a level-2 or level-3 sub-task always takes the root\'s fase, ignoring the submitted one', function () {
    $actor = stageFollowActor();
    $workOrder = WorkOrder::factory()->create();
    $stageA = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $otherStage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();

    $root = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);
    $level2 = Task::factory()->forCreator($actor)->childOf($root)->create(['work_order_id' => $workOrder->id]);
    $level3 = Task::factory()->forCreator($actor)->childOf($level2)->create(['work_order_id' => $workOrder->id]);

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/time-entries', [
        'date' => '2026-09-25',
        'task_type_id' => TaskType::factory()->create()->id,
        'minutes' => 30,
        'task_id' => $level3->id,
        'work_order_stage_id' => $otherStage->id,
    ])->assertCreated();

    expect($response->json('data.work_order_stage.id'))->toBe($stageA->id);
    $this->assertDatabaseHas('time_entries', ['id' => $response->json('data.id'), 'work_order_stage_id' => $stageA->id]);
});

it('AC-003: a voce logged on a sub-task of a root with no fase is saved with no fase', function () {
    $actor = stageFollowActor();
    $workOrder = WorkOrder::factory()->create();

    $root = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => null]);
    $subtask = Task::factory()->forCreator($actor)->childOf($root)->create(['work_order_id' => $workOrder->id]);

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/time-entries', [
        'date' => '2026-09-25',
        'task_type_id' => TaskType::factory()->create()->id,
        'minutes' => 30,
        'task_id' => $subtask->id,
    ])->assertCreated();

    expect($response->json('data.work_order_stage'))->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-004 — every level of the tree follows the root's own fase change
// ---------------------------------------------------------------------------

it('AC-004: every voce of a multi-level tree takes the root\'s new fase when the root changes stage', function () {
    $actor = stageFollowActor();
    $workOrder = WorkOrder::factory()->create();
    $stageA = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $stageB = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();

    $root = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);
    $level2 = Task::factory()->forCreator($actor)->childOf($root)->create(['work_order_id' => $workOrder->id]);
    $level3 = Task::factory()->forCreator($actor)->childOf($level2)->create(['work_order_id' => $workOrder->id]);

    $rootEntry = TimeEntry::factory()->forUser($actor)->create(['task_id' => $root->id, 'work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);
    $level2Entry = TimeEntry::factory()->forUser($actor)->create(['task_id' => $level2->id, 'work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);
    $level3Entry = TimeEntry::factory()->forUser($actor)->create(['task_id' => $level3->id, 'work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$root->id}", ['work_order_stage_id' => $stageB->id])->assertOk();

    foreach ([$rootEntry, $level2Entry, $level3Entry] as $entry) {
        $this->assertDatabaseHas('time_entries', ['id' => $entry->id, 'work_order_stage_id' => $stageB->id]);
    }
});

// ---------------------------------------------------------------------------
// AC-005 — "Senza fase" (board or PATCH null) or a commessa change detaches
// the whole tree's voci to null
// ---------------------------------------------------------------------------

it('AC-005: PATCH-ing a root to "Senza fase" nulls its tree\'s voci', function () {
    $actor = stageFollowActor();
    $workOrder = WorkOrder::factory()->create();
    $stageA = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();

    $root = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);
    $subtask = Task::factory()->forCreator($actor)->childOf($root)->create(['work_order_id' => $workOrder->id]);
    $rootEntry = TimeEntry::factory()->forUser($actor)->create(['task_id' => $root->id, 'work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);
    $subtaskEntry = TimeEntry::factory()->forUser($actor)->create(['task_id' => $subtask->id, 'work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$root->id}", ['work_order_stage_id' => null])->assertOk();

    $this->assertDatabaseHas('time_entries', ['id' => $rootEntry->id, 'work_order_stage_id' => null]);
    $this->assertDatabaseHas('time_entries', ['id' => $subtaskEntry->id, 'work_order_stage_id' => null]);
});

it('AC-005: moving a root task to a different commessa detaches its inherited fase and nulls its voci', function () {
    $actor = stageFollowActor();
    $workOrder = WorkOrder::factory()->create();
    $otherWorkOrder = WorkOrder::factory()->create();
    $stageA = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();

    $root = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);
    $entry = TimeEntry::factory()->forUser($actor)->create(['task_id' => $root->id, 'work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$root->id}", ['work_order_id' => $otherWorkOrder->id])->assertOk();

    $this->assertDatabaseHas('time_entries', ['id' => $entry->id, 'work_order_stage_id' => null]);
});

// ---------------------------------------------------------------------------
// AC-006 — reparenting a sub-task realigns only ITS OWN subtree; the old
// tree's unmoved voci stay put
// ---------------------------------------------------------------------------

it('AC-006: reparenting a sub-task under a root of a different fase realigns only its own subtree', function () {
    $actor = stageFollowActor();
    $workOrder = WorkOrder::factory()->create();
    $stageA = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $stageB = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();

    $oldRoot = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);
    $moving = Task::factory()->forCreator($actor)->childOf($oldRoot)->create(['work_order_id' => $workOrder->id]);
    $movingChild = Task::factory()->forCreator($actor)->childOf($moving)->create(['work_order_id' => $workOrder->id]);
    $newRoot = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageB->id]);

    $oldRootEntry = TimeEntry::factory()->forUser($actor)->create(['task_id' => $oldRoot->id, 'work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);
    $movingEntry = TimeEntry::factory()->forUser($actor)->create(['task_id' => $moving->id, 'work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);
    $movingChildEntry = TimeEntry::factory()->forUser($actor)->create(['task_id' => $movingChild->id, 'work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$moving->id}", ['parent_task_id' => $newRoot->id])->assertOk();

    $this->assertDatabaseHas('time_entries', ['id' => $movingEntry->id, 'work_order_stage_id' => $stageB->id]);
    $this->assertDatabaseHas('time_entries', ['id' => $movingChildEntry->id, 'work_order_stage_id' => $stageB->id]);
    $this->assertDatabaseHas('time_entries', ['id' => $oldRootEntry->id, 'work_order_stage_id' => $stageA->id]);
});

it('AC-006: detaching a sub-task into a root with no fase nulls its own subtree\'s voci', function () {
    $actor = stageFollowActor();
    $workOrder = WorkOrder::factory()->create();
    $stageA = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();

    $oldRoot = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);
    $moving = Task::factory()->forCreator($actor)->childOf($oldRoot)->create(['work_order_id' => $workOrder->id]);
    $movingEntry = TimeEntry::factory()->forUser($actor)->create(['task_id' => $moving->id, 'work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$moving->id}", ['parent_task_id' => null])->assertOk();

    $this->assertDatabaseHas('time_entries', ['id' => $movingEntry->id, 'work_order_stage_id' => null]);
});

// ---------------------------------------------------------------------------
// AC-007 — voci follow the task even across a closed fase at either end;
// standalone voci (no task) are never touched by a task write
// ---------------------------------------------------------------------------

it('AC-007: reparenting across two CLOSED stages still realigns the moved subtree', function () {
    $actor = stageFollowActor();
    $workOrder = WorkOrder::factory()->create();
    $closedOrigin = WorkOrderStage::factory()->forWorkOrder($workOrder)->closed()->create();
    $closedDestination = WorkOrderStage::factory()->forWorkOrder($workOrder)->closed()->create();

    $oldRoot = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $closedOrigin->id]);
    $moving = Task::factory()->forCreator($actor)->childOf($oldRoot)->create(['work_order_id' => $workOrder->id]);
    $newRoot = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $closedDestination->id]);
    $movingEntry = TimeEntry::factory()->forUser($actor)->create(['task_id' => $moving->id, 'work_order_id' => $workOrder->id, 'work_order_stage_id' => $closedOrigin->id]);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$moving->id}", ['parent_task_id' => $newRoot->id])->assertOk();

    $this->assertDatabaseHas('time_entries', ['id' => $movingEntry->id, 'work_order_stage_id' => $closedDestination->id]);
});

it('AC-007: a standalone voce with its own fase (no task) is never touched by an unrelated task write', function () {
    $actor = stageFollowActor();
    $workOrder = WorkOrder::factory()->create();
    $stageA = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $stageB = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();

    $standaloneEntry = TimeEntry::factory()->forUser($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id, 'task_id' => null]);
    $root = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$root->id}", ['work_order_stage_id' => $stageB->id])->assertOk();

    $this->assertDatabaseHas('time_entries', ['id' => $standaloneEntry->id, 'work_order_stage_id' => $stageA->id]);
});

// ---------------------------------------------------------------------------
// AC-008 — no backfill: an already-misaligned voce is left alone by a PATCH
// that changes neither fase nor parent
// ---------------------------------------------------------------------------

it('AC-008: a PATCH that changes neither fase nor parent leaves an already-misaligned voce as it is', function () {
    $actor = stageFollowActor();
    $workOrder = WorkOrder::factory()->create();
    $stageA = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $driftedStage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();

    $root = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);
    $entry = TimeEntry::factory()->forUser($actor)->create(['task_id' => $root->id, 'work_order_id' => $workOrder->id, 'work_order_stage_id' => $driftedStage->id]);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$root->id}", ['title' => 'Solo il titolo cambia'])->assertOk();

    $this->assertDatabaseHas('time_entries', ['id' => $entry->id, 'work_order_stage_id' => $driftedStage->id]);
});

// ---------------------------------------------------------------------------
// AC-009 — a refused fase change (409 closed stage) touches no voce at all
// ---------------------------------------------------------------------------

it('AC-009: a PATCH refused with 409 (closed destination stage) leaves every voce untouched', function () {
    $actor = stageFollowActor();
    $workOrder = WorkOrder::factory()->create();
    $stageA = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $closedStage = WorkOrderStage::factory()->forWorkOrder($workOrder)->closed()->create();

    $root = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);
    $entry = TimeEntry::factory()->forUser($actor)->create(['task_id' => $root->id, 'work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$root->id}", ['work_order_stage_id' => $closedStage->id])->assertStatus(409);

    $this->assertDatabaseHas('time_entries', ['id' => $entry->id, 'work_order_stage_id' => $stageA->id]);
    $this->assertDatabaseHas('tasks', ['id' => $root->id, 'work_order_stage_id' => $stageA->id]);
});

// ---------------------------------------------------------------------------
// AC-010 — GET task-board reflects the shifted minutes after a move
// ---------------------------------------------------------------------------

it('AC-010: GET task-board reports logged_minutes/unstaged_logged_minutes coherent with a move', function () {
    $actor = stageFollowActor();
    $workOrder = WorkOrder::factory()->create();
    $stageA = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $stageB = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();

    $root = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);
    TimeEntry::factory()->forUser($actor)->create(['task_id' => $root->id, 'work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id, 'minutes' => 40]);

    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/move", [
        'task_id' => $root->id,
        'work_order_stage_id' => $stageB->id,
        'position' => 0,
    ])->assertOk();

    $stages = collect($this->getJson("/api/work-orders/{$workOrder->id}/task-board")->assertOk()->json('data.stages'));

    expect($stages->firstWhere('id', $stageA->id)['logged_minutes'])->toBe(0)
        ->and($stages->firstWhere('id', $stageB->id)['logged_minutes'])->toBe(40);
});
