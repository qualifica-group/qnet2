<?php

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Commessa "Fasi" CRUD + lifecycle (spec 0146, BE-4, AC-005..AC-010)
|--------------------------------------------------------------------------
|
| Every actor uses the repo's own `workOrderUserWith()` helper (see
| WorkOrderCrudTest), which grants `work-orders.viewAll` on top so a 403 here
| always means "missing update", never "not a member" (WorkOrderVisibilityTest
| owns that scoping). Re-declared here (guarded) per the repo's shared-Pest-
| helper idiom: a directory-scoped `php artisan test` run never loads
| WorkOrderCrudTest.php, so the global function would otherwise be undefined.
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

it('POST stages: creates a stage accoded at the end, PATCH renames it (AC-005)', function () {
    $actor = workOrderUserWith(['view', 'update']);
    $workOrder = WorkOrder::factory()->create();
    WorkOrderStage::factory()->forWorkOrder($workOrder)->atPosition(0)->create();
    Sanctum::actingAs($actor);

    $store = $this->postJson("/api/work-orders/{$workOrder->id}/stages", ['name' => 'Progettazione'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Progettazione')
        ->assertJsonPath('data.sort_order', 1);

    $stageId = $store->json('data.id');

    $this->patchJson("/api/work-orders/{$workOrder->id}/stages/{$stageId}", ['name' => 'Progettazione avanzata'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Progettazione avanzata');

    $this->assertDatabaseHas('work_order_stages', ['id' => $stageId, 'name' => 'Progettazione avanzata']);
});

it('DELETE stages/{stage}: demotes its tasks to "Senza fase", appended at the end, never deletes them (AC-005)', function () {
    $actor = workOrderUserWith(['view', 'update']);
    $workOrder = WorkOrder::factory()->create();
    $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();

    $alreadyUnstaged = Task::factory()->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => null, 'stage_position' => 0]);
    $first = Task::factory()->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stage->id, 'stage_position' => 0]);
    $second = Task::factory()->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stage->id, 'stage_position' => 1]);

    Sanctum::actingAs($actor);

    $this->deleteJson("/api/work-orders/{$workOrder->id}/stages/{$stage->id}")
        ->assertOk()
        ->assertJsonPath('data', null);

    $this->assertDatabaseMissing('work_order_stages', ['id' => $stage->id]);
    $this->assertDatabaseHas('tasks', ['id' => $alreadyUnstaged->id, 'work_order_stage_id' => null, 'stage_position' => 0]);
    $this->assertDatabaseHas('tasks', ['id' => $first->id, 'work_order_stage_id' => null, 'stage_position' => 1]);
    $this->assertDatabaseHas('tasks', ['id' => $second->id, 'work_order_stage_id' => null, 'stage_position' => 2]);
});

it('POST stages/reorder: an exact permutation resequences sort_order (AC-006)', function () {
    $actor = workOrderUserWith(['view', 'update']);
    $workOrder = WorkOrder::factory()->create();
    $a = WorkOrderStage::factory()->forWorkOrder($workOrder)->atPosition(0)->create();
    $b = WorkOrderStage::factory()->forWorkOrder($workOrder)->atPosition(1)->create();
    $c = WorkOrderStage::factory()->forWorkOrder($workOrder)->atPosition(2)->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/stages/reorder", ['stage_ids' => [$c->id, $a->id, $b->id]])
        ->assertOk()
        ->assertJsonPath('data.0.id', $c->id)
        ->assertJsonPath('data.1.id', $a->id)
        ->assertJsonPath('data.2.id', $b->id);

    $this->assertDatabaseHas('work_order_stages', ['id' => $c->id, 'sort_order' => 0]);
    $this->assertDatabaseHas('work_order_stages', ['id' => $a->id, 'sort_order' => 1]);
    $this->assertDatabaseHas('work_order_stages', ['id' => $b->id, 'sort_order' => 2]);
});

it('POST stages/reorder: missing, foreign or duplicate ids 422 (AC-006)', function () {
    $actor = workOrderUserWith(['view', 'update']);
    $workOrder = WorkOrder::factory()->create();
    $a = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $b = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $foreign = WorkOrderStage::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/stages/reorder", ['stage_ids' => [$a->id]])
        ->assertStatus(422);

    $this->postJson("/api/work-orders/{$workOrder->id}/stages/reorder", ['stage_ids' => [$a->id, $foreign->id]])
        ->assertStatus(422);

    $this->postJson("/api/work-orders/{$workOrder->id}/stages/reorder", ['stage_ids' => [$a->id, $a->id]])
        ->assertStatus(422);

    $this->assertDatabaseHas('work_order_stages', ['id' => $b->id]);
});

it('a stage belonging to another commessa 404s in the URL (AC-007, scopeBindings)', function () {
    $actor = workOrderUserWith(['view', 'update']);
    $workOrder = WorkOrder::factory()->create();
    $otherWorkOrder = WorkOrder::factory()->create();
    $foreignStage = WorkOrderStage::factory()->forWorkOrder($otherWorkOrder)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$workOrder->id}/stages/{$foreignStage->id}", ['name' => 'x'])
        ->assertNotFound();

    $this->deleteJson("/api/work-orders/{$workOrder->id}/stages/{$foreignStage->id}")
        ->assertNotFound();

    $this->postJson("/api/work-orders/{$workOrder->id}/stages/{$foreignStage->id}/close")
        ->assertNotFound();
});

it('POST stages/{stage}/close: 409 with open_tasks_count while a root or a sub-task is not closed, closes once all are (AC-008)', function () {
    $actor = workOrderUserWith(['view', 'update']);
    $workOrder = WorkOrder::factory()->create();
    $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $openStatus = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    $closedStatus = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();

    $root = Task::factory()->inStatus($closedStatus)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stage->id]);
    $openSubtask = Task::factory()->inStatus($openStatus)->childOf($root)->create(['work_order_id' => $workOrder->id]);

    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/stages/{$stage->id}/close")
        ->assertStatus(409)
        ->assertJsonPath('data.open_tasks_count', 1);

    $openSubtask->update(['task_status_id' => $closedStatus->id]);

    $response = $this->postJson("/api/work-orders/{$workOrder->id}/stages/{$stage->id}/close")
        ->assertOk();

    expect($response->json('data.closed_at'))->not->toBeNull()
        ->and($response->json('data.closed_by.id'))->toBe($actor->id);

    $this->postJson("/api/work-orders/{$workOrder->id}/stages/{$stage->id}/reopen")
        ->assertOk()
        ->assertJsonPath('data.closed_at', null);
});

it('BaseApiController::fail() carries data only when given one: the open-tasks 409 has it, a plain message-only 409 does not', function () {
    $actor = workOrderUserWith(['view', 'update']);

    $workOrder = WorkOrder::factory()->create();
    $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $openStatus = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    Task::factory()->inStatus($openStatus)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stage->id]);

    $closedWorkOrder = WorkOrder::factory()->forceClosed()->create();
    $closedWorkOrderStage = WorkOrderStage::factory()->forWorkOrder($closedWorkOrder)->create();

    Sanctum::actingAs($actor);

    // WorkOrderStageHasOpenTasksException path: $this->fail($message, 409, data: [...]).
    $withData = $this->postJson("/api/work-orders/{$workOrder->id}/stages/{$stage->id}/close")
        ->assertStatus(409);
    expect($withData->json())->toHaveKeys(['success', 'message', 'data'])
        ->and($withData->json('data.open_tasks_count'))->toBe(1);

    // Generic Throwable path (WorkOrderClosedGuard's plain abort(409, ...)):
    // the existing two-arg fail($message, $status) call sites keep their
    // EXACT prior shape — no `data` key at all, proving the new optional
    // parameter is purely additive (backward compatible).
    $withoutData = $this->postJson("/api/work-orders/{$closedWorkOrder->id}/stages/{$closedWorkOrderStage->id}/close")
        ->assertStatus(409);
    expect($withoutData->json())->toHaveKeys(['success', 'message'])
        ->and($withoutData->json())->not->toHaveKey('data');
});

it('every stage endpoint 403s without update on the commessa (AC-009)', function () {
    $actor = workOrderUserWith(['view']);
    $workOrder = WorkOrder::factory()->create();
    $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/stages", ['name' => 'x'])->assertForbidden();
    $this->patchJson("/api/work-orders/{$workOrder->id}/stages/{$stage->id}", ['name' => 'x'])->assertForbidden();
    $this->deleteJson("/api/work-orders/{$workOrder->id}/stages/{$stage->id}")->assertForbidden();
    $this->postJson("/api/work-orders/{$workOrder->id}/stages/reorder", ['stage_ids' => [$stage->id]])->assertForbidden();
    $this->postJson("/api/work-orders/{$workOrder->id}/stages/{$stage->id}/close")->assertForbidden();
    $this->postJson("/api/work-orders/{$workOrder->id}/stages/{$stage->id}/reopen")->assertForbidden();
});

it('the "commessa chiusa" and "fase con task aperti" 409 messages are translated in Italian (constraints, i18n)', function () {
    $actor = workOrderUserWith(['view', 'update']);
    $closedWorkOrder = WorkOrder::factory()->forceClosed()->create();
    $closedStage = WorkOrderStage::factory()->forWorkOrder($closedWorkOrder)->create();

    $workOrder = WorkOrder::factory()->create();
    $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $openStatus = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    Task::factory()->inStatus($openStatus)->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $stage->id]);

    Sanctum::actingAs($actor);

    // SetLocale (middleware) derives the response language from the
    // request's OWN `Accept-Language` header, not from `app()->setLocale()`
    // called ahead of time — the header is what the frontend actually sends.
    $this->withHeader('Accept-Language', 'it')
        ->postJson("/api/work-orders/{$closedWorkOrder->id}/stages/{$closedStage->id}/close")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Questa commessa è chiusa.');

    $this->withHeader('Accept-Language', 'it')
        ->postJson("/api/work-orders/{$workOrder->id}/stages/{$stage->id}/close")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Questa fase ha ancora task aperti: chiudili prima di chiudere la fase.');
});

it('every stage mutation 409s once the commessa is force-closed (AC-010)', function () {
    $actor = workOrderUserWith(['view', 'update']);
    $workOrder = WorkOrder::factory()->forceClosed()->create();
    $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/stages", ['name' => 'x'])->assertStatus(409);
    $this->patchJson("/api/work-orders/{$workOrder->id}/stages/{$stage->id}", ['name' => 'x'])->assertStatus(409);
    $this->deleteJson("/api/work-orders/{$workOrder->id}/stages/{$stage->id}")->assertStatus(409);
    $this->postJson("/api/work-orders/{$workOrder->id}/stages/reorder", ['stage_ids' => [$stage->id]])->assertStatus(409);
    $this->postJson("/api/work-orders/{$workOrder->id}/stages/{$stage->id}/close")->assertStatus(409);
    $this->postJson("/api/work-orders/{$workOrder->id}/stages/{$stage->id}/reopen")->assertStatus(409);
});

it('GET stages lists the select option set for the task form', function () {
    $actor = workOrderUserWith(['view']);
    $workOrder = WorkOrder::factory()->create();
    WorkOrderStage::factory()->forWorkOrder($workOrder)->atPosition(1)->create(['name' => 'B']);
    WorkOrderStage::factory()->forWorkOrder($workOrder)->atPosition(0)->create(['name' => 'A']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/work-orders/{$workOrder->id}/stages")
        ->assertOk()
        ->assertJsonPath('data.0.name', 'A')
        ->assertJsonPath('data.1.name', 'B');
});
