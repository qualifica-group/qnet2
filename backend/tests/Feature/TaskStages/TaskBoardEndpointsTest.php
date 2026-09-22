<?php

use App\Models\Task;
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
| Task board read + move (spec 0146, BE-4, AC-011..AC-014, D-9)
|--------------------------------------------------------------------------
|
| Reuses `workOrderUserWith()` (WorkOrderCrudTest), re-declared here
| (guarded) per the repo's shared-Pest-helper idiom: a directory-scoped
| `php artisan test` run never loads WorkOrderCrudTest.php, so the global
| function would otherwise be undefined.
*/

if (! function_exists('workOrderUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function workOrderUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("work-orders.{$ability}");
        }

        $user->givePermissionTo('work-orders.viewAll');

        return $user;
    }
}

if (! function_exists('boardActor')) {
    /**
     * A single actor combining `work-orders.*` and `tasks.*` abilities — the
     * two resources the board's own `show()` gates on together (D-9).
     *
     * @param  array<int, string>  $workOrderAbilities
     * @param  array<int, string>  $taskAbilities
     */
    function boardActor(array $workOrderAbilities, array $taskAbilities = ['viewAny']): User
    {
        $actor = workOrderUserWith($workOrderAbilities);

        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }

        foreach ($taskAbilities as $ability) {
            $actor->givePermissionTo("tasks.{$ability}");
        }

        return $actor;
    }
}

it('GET task-board: only visible tasks of THIS commessa, subtasks included, actual_minutes summed (AC-011)', function () {
    $actor = boardActor(['view']);
    $workOrder = WorkOrder::factory()->create();
    $otherWorkOrder = WorkOrder::factory()->create();

    $visibleRoot = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id]);
    $subtask = Task::factory()->forCreator($actor)->childOf($visibleRoot)->create(['work_order_id' => $workOrder->id]);
    $invisibleRoot = Task::factory()->create(['work_order_id' => $workOrder->id]); // no relation to $actor
    Task::factory()->forCreator($actor)->create(['work_order_id' => $otherWorkOrder->id]); // other commessa

    TimeEntry::factory()->create(['task_id' => $visibleRoot->id, 'minutes' => 30]);
    TimeEntry::factory()->create(['task_id' => $visibleRoot->id, 'minutes' => 45]);

    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/work-orders/{$workOrder->id}/task-board")->assertOk();

    $taskIds = collect($response->json('data.tasks'))->pluck('id');

    expect($taskIds)->toContain($visibleRoot->id, $subtask->id)
        ->and($taskIds)->not->toContain($invisibleRoot->id);

    $boardRoot = collect($response->json('data.tasks'))->firstWhere('id', $visibleRoot->id);
    expect($boardRoot)->toHaveKey('task_importance')
        ->and($boardRoot['actual_minutes'])->toBe(75)
        ->and($boardRoot['permissions'])->toHaveKeys(['resource', 'fields', 'actions', 'change_requestable_fields']);
});

it('GET task-board: description_excerpt is the plain-text description, cut at 160 characters, null when empty', function () {
    $actor = boardActor(['view']);
    $workOrder = WorkOrder::factory()->create();

    $long = Task::factory()->forCreator($actor)->create([
        'work_order_id' => $workOrder->id,
        'description' => '<p>Sopralluogo <strong>impianto</strong></p><p>'.str_repeat('a', 300).'</p>',
    ]);
    $empty = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'description' => '<p></p>']);

    Sanctum::actingAs($actor);

    $tasks = collect($this->getJson("/api/work-orders/{$workOrder->id}/task-board")->assertOk()->json('data.tasks'));
    $excerpt = $tasks->firstWhere('id', $long->id)['description_excerpt'];

    expect($excerpt)->toStartWith('Sopralluogo impianto a')
        ->and($excerpt)->not->toContain('<')
        ->and(mb_strlen($excerpt))->toBeLessThanOrEqual(163)
        ->and($tasks->firstWhere('id', $empty->id)['description_excerpt'])->toBeNull();
});

it('GET task-board: 403 without tasks.viewAny (AC-012)', function () {
    $actor = workOrderUserWith(['view']);
    $workOrder = WorkOrder::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/work-orders/{$workOrder->id}/task-board")->assertForbidden();
});

it('GET task-board: is_read_only reflects a closed commessa or the lack of update (AC-010, D-9)', function () {
    $viewer = boardActor(['view']);
    $manager = boardActor(['view', 'update']);
    $workOrder = WorkOrder::factory()->create();
    $closedWorkOrder = WorkOrder::factory()->forceClosed()->create();

    Sanctum::actingAs($viewer);
    $this->getJson("/api/work-orders/{$workOrder->id}/task-board")->assertOk()->assertJsonPath('data.is_read_only', true);

    Sanctum::actingAs($manager);
    $this->getJson("/api/work-orders/{$workOrder->id}/task-board")->assertOk()->assertJsonPath('data.is_read_only', false);
    $this->getJson("/api/work-orders/{$closedWorkOrder->id}/task-board")->assertOk()->assertJsonPath('data.is_read_only', true);
});

it('POST task-board/move: moves a task across stages, both groups stay compact 0..n-1 (AC-013)', function () {
    $actor = boardActor(['view', 'update'], ['viewAny', 'update']);
    $workOrder = WorkOrder::factory()->create();
    $origin = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $destination = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();

    $moving = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $origin->id, 'stage_position' => 0]);
    $originSibling = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $origin->id, 'stage_position' => 1]);
    $destinationExisting = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $destination->id, 'stage_position' => 0]);

    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/move", [
        'task_id' => $moving->id,
        'work_order_stage_id' => $destination->id,
        'position' => 0,
    ])->assertOk();

    $this->assertDatabaseHas('tasks', ['id' => $moving->id, 'work_order_stage_id' => $destination->id, 'stage_position' => 0]);
    $this->assertDatabaseHas('tasks', ['id' => $destinationExisting->id, 'work_order_stage_id' => $destination->id, 'stage_position' => 1]);
    $this->assertDatabaseHas('tasks', ['id' => $originSibling->id, 'work_order_stage_id' => $origin->id, 'stage_position' => 0]);
});

it('POST task-board/move: 422 on a sub-task, a foreign task, or a foreign stage; 409 on a closed stage; 403 without canUpdate (AC-014)', function () {
    $actor = boardActor(['view', 'update'], ['viewAny', 'update']);
    $outsider = boardActor(['view'], ['viewAny']);
    $workOrder = WorkOrder::factory()->create();
    $otherWorkOrder = WorkOrder::factory()->create();
    $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $closedStage = WorkOrderStage::factory()->forWorkOrder($workOrder)->closed()->create();
    $foreignStage = WorkOrderStage::factory()->forWorkOrder($otherWorkOrder)->create();

    $root = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id]);
    $subtask = Task::factory()->forCreator($actor)->childOf($root)->create(['work_order_id' => $workOrder->id]);
    $foreignTask = Task::factory()->forCreator($actor)->create(['work_order_id' => $otherWorkOrder->id]);

    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/move", ['task_id' => $subtask->id, 'work_order_stage_id' => $stage->id, 'position' => 0])
        ->assertStatus(422);

    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/move", ['task_id' => $foreignTask->id, 'work_order_stage_id' => $stage->id, 'position' => 0])
        ->assertStatus(422);

    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/move", ['task_id' => $root->id, 'work_order_stage_id' => $foreignStage->id, 'position' => 0])
        ->assertStatus(422);

    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/move", ['task_id' => $root->id, 'work_order_stage_id' => $closedStage->id, 'position' => 0])
        ->assertStatus(409);

    Sanctum::actingAs($outsider);
    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/move", ['task_id' => $root->id, 'work_order_stage_id' => $stage->id, 'position' => 0])
        ->assertForbidden();
});

it('POST task-board/move: 409 once the commessa itself is closed (D-9)', function () {
    $actor = boardActor(['view', 'update'], ['viewAny', 'update']);
    $workOrder = WorkOrder::factory()->forceClosed()->create();
    $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $root = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/move", ['task_id' => $root->id, 'work_order_stage_id' => $stage->id, 'position' => 0])
        ->assertStatus(409);
});
