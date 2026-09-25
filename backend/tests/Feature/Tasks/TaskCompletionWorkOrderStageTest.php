<?php

use App\Models\Task;
use App\Models\TaskType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| `time_entry.work_order_stage_id` on task completion (spec 0163, AC-006)
|--------------------------------------------------------------------------
|
| Completing a Task creates its segnatempo through the same
| TimeEntryLinkResolver::fromTask() path the generic POST uses whenever
| `task_id` is set — this AC exists purely to prove the two write paths
| land on the SAME derived stage, not a second implementation of D-1.
*/

if (! function_exists('stageCompletionActorWith')) {
    /**
     * @param  array<int, string>  $taskAbilities
     */
    function stageCompletionActorWith(array $taskAbilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo('tasks.viewAll');

        foreach ($taskAbilities as $ability) {
            $user->givePermissionTo("tasks.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('stageBulkActorWith')) {
    /**
     * AC-006's bulk-complete case additionally authorizes on the commessa
     * itself (`WorkOrderTaskBoardController::bulk()` -> `authorize('view',
     * $workOrder)`), on top of the per-row `tasks.complete` re-asserted by
     * `TaskBulkActionService`.
     */
    function stageBulkActorWith(): User
    {
        $actor = stageCompletionActorWith(['complete']);

        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }
        $actor->givePermissionTo(['work-orders.view', 'work-orders.viewAll']);

        return $actor;
    }
}

if (! function_exists('stageValidTimeEntryPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function stageValidTimeEntryPayload(array $overrides = []): array
    {
        return array_merge([
            'date' => '2026-09-14',
            'task_type_id' => TaskType::factory()->create()->id,
            'minutes' => 60,
        ], $overrides);
    }
}

it('AC-006: completing a task in a stage creates the time_entry with that stage', function () {
    $actor = stageCompletionActorWith(['complete']);
    $workOrder = WorkOrder::factory()->create();
    $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $task = Task::factory()->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stage->id]);
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => stageValidTimeEntryPayload()])
        ->assertOk();

    $this->assertDatabaseHas('time_entries', ['task_id' => $task->id, 'work_order_stage_id' => $stage->id]);
});

it('AC-006: completing a task with no stage creates the time_entry with no stage', function () {
    $actor = stageCompletionActorWith(['complete']);
    $task = Task::factory()->create(['work_order_id' => WorkOrder::factory()->create()->id, 'work_order_stage_id' => null]);
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => stageValidTimeEntryPayload()])
        ->assertOk();

    $this->assertDatabaseHas('time_entries', ['task_id' => $task->id, 'work_order_stage_id' => null]);
});

it('AC-006: bulk-completing tasks in different stages creates each time_entry with its own task\'s stage', function () {
    $actor = stageBulkActorWith();
    $workOrder = WorkOrder::factory()->create();
    $stageA = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $stageB = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $taskA = Task::factory()->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageA->id]);
    $taskB = Task::factory()->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stageB->id]);
    $taskA->assignees()->attach($actor->id);
    $taskB->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson('/api/work-orders/'.$workOrder->id.'/task-board/bulk', [
        'task_ids' => [$taskA->id, $taskB->id],
        'action' => 'complete',
        'time_entry' => stageValidTimeEntryPayload(),
    ])->assertOk();

    $this->assertDatabaseHas('time_entries', ['task_id' => $taskA->id, 'work_order_stage_id' => $stageA->id]);
    $this->assertDatabaseHas('time_entries', ['task_id' => $taskB->id, 'work_order_stage_id' => $stageB->id]);
});
