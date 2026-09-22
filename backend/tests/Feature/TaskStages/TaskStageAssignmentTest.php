<?php

use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| work_order_stage_id on a Task's own create/update (spec 0146, BE-6,
| AC-015/AC-016, D-3)
|--------------------------------------------------------------------------
*/

if (! function_exists('taskActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function taskActorWith(array $abilities, bool $withViewAll = true): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("tasks.{$ability}");
        }

        if ($withViewAll) {
            $user->givePermissionTo('tasks.viewAll');
        }

        return $user;
    }
}

if (! function_exists('taskPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function taskPayload(array $overrides = []): array
    {
        return [
            'title' => 'Prima attivita',
            'requester_id' => User::factory()->create()->id,
            'assignee_ids' => [User::factory()->create()->id],
            'end_date' => '2026-12-31',
            ...$overrides,
        ];
    }
}

it('POST /api/tasks: a valid work_order_stage_id is accepted and accoded at the end (AC-015)', function () {
    $actor = taskActorWith(['create']);
    $workOrder = WorkOrder::factory()->create();
    $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $existing = Task::factory()->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stage->id, 'stage_position' => 0]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks', taskPayload([
        'work_order_id' => $workOrder->id,
        'work_order_stage_id' => $stage->id,
    ]))->assertCreated();

    expect($response->json('data.work_order_stage_id'))->toBe($stage->id)
        ->and($response->json('data.work_order_stage.id'))->toBe($stage->id);

    $newTaskId = $response->json('data.id');
    $this->assertDatabaseHas('tasks', ['id' => $newTaskId, 'work_order_stage_id' => $stage->id, 'stage_position' => 1]);
    $this->assertDatabaseHas('tasks', ['id' => $existing->id, 'stage_position' => 0]);
});

it('POST /api/tasks: a stage of another commessa is 422 on work_order_stage_id (AC-015)', function () {
    $actor = taskActorWith(['create']);
    $workOrder = WorkOrder::factory()->create();
    $otherWorkOrder = WorkOrder::factory()->create();
    $foreignStage = WorkOrderStage::factory()->forWorkOrder($otherWorkOrder)->create();
    Sanctum::actingAs($actor);

    // A resulting-state 422 (TaskStageGuard, same posture as
    // TaskReferentRegistryGuard), not a FormRequest field error: the
    // envelope carries `message`, never `errors.work_order_stage_id`.
    $this->postJson('/api/tasks', taskPayload([
        'work_order_id' => $workOrder->id,
        'work_order_stage_id' => $foreignStage->id,
    ]))->assertStatus(422);
});

it('POST /api/tasks: work_order_stage_id together with parent_task_id is 422 (D-3)', function () {
    $actor = taskActorWith(['create', 'update']);
    $workOrder = WorkOrder::factory()->create();
    $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $parent = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', taskPayload([
        'work_order_id' => $workOrder->id,
        'parent_task_id' => $parent->id,
        'work_order_stage_id' => $stage->id,
    ]))->assertStatus(422);
});

it('POST /api/tasks: a closed stage is 409 (D-4)', function () {
    $actor = taskActorWith(['create']);
    $workOrder = WorkOrder::factory()->create();
    $closedStage = WorkOrderStage::factory()->forWorkOrder($workOrder)->closed()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', taskPayload([
        'work_order_id' => $workOrder->id,
        'work_order_stage_id' => $closedStage->id,
    ]))->assertStatus(409);
});

it('PATCH /api/tasks/{task}: reassigning to a valid stage of the same commessa accodas the task (AC-015)', function () {
    $actor = taskActorWith(['update']);
    $workOrder = WorkOrder::factory()->create();
    $origin = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $destination = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $existingInDestination = Task::factory()->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $destination->id, 'stage_position' => 0]);
    $task = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $origin->id, 'stage_position' => 0]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['work_order_stage_id' => $destination->id])
        ->assertOk()
        ->assertJsonPath('data.work_order_stage_id', $destination->id);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'work_order_stage_id' => $destination->id, 'stage_position' => 1]);
    $this->assertDatabaseHas('tasks', ['id' => $existingInDestination->id, 'stage_position' => 0]);
});

it('PATCH /api/tasks/{task}: changing or emptying work_order_id silently detaches an untouched stage (AC-016)', function () {
    $actor = taskActorWith(['update']);
    $workOrder = WorkOrder::factory()->create();
    $otherWorkOrder = WorkOrder::factory()->create();
    $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $task = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stage->id, 'stage_position' => 0]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['work_order_id' => $otherWorkOrder->id])
        ->assertOk()
        ->assertJsonPath('data.work_order_stage_id', null);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'work_order_id' => $otherWorkOrder->id, 'work_order_stage_id' => null]);

    // Emptying it outright has the same effect.
    $task->update(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stage->id]);

    $this->patchJson("/api/tasks/{$task->id}", ['work_order_id' => null])
        ->assertOk()
        ->assertJsonPath('data.work_order_stage_id', null);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'work_order_id' => null, 'work_order_stage_id' => null]);
});

it('PATCH /api/tasks/{task}: becoming a sub-task silently detaches an untouched stage (AC-016)', function () {
    $actor = taskActorWith(['update']);
    $workOrder = WorkOrder::factory()->create();
    $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $parent = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id]);
    $task = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stage->id, 'stage_position' => 0]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['parent_task_id' => $parent->id])
        ->assertOk()
        ->assertJsonPath('data.work_order_stage_id', null);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'parent_task_id' => $parent->id, 'work_order_stage_id' => null]);
});

it('PATCH /api/tasks/{task}: an explicit non-null work_order_stage_id on a sub-task is 422 (D-3)', function () {
    $actor = taskActorWith(['update']);
    $workOrder = WorkOrder::factory()->create();
    $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $parent = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id]);
    $subtask = Task::factory()->forCreator($actor)->childOf($parent)->create(['work_order_id' => $workOrder->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$subtask->id}", ['work_order_stage_id' => $stage->id])
        ->assertStatus(422);
});

it('PATCH /api/tasks/{task}: a closed destination stage is 409 (D-4)', function () {
    $actor = taskActorWith(['update']);
    $workOrder = WorkOrder::factory()->create();
    $closedStage = WorkOrderStage::factory()->forWorkOrder($workOrder)->closed()->create();
    $task = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['work_order_stage_id' => $closedStage->id])
        ->assertStatus(409);
});
