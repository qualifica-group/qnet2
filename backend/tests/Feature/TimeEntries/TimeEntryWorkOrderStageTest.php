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
| TimeEntry `work_order_stage_id` (spec 0163, D-1..D-3, AC-001..AC-004)
|--------------------------------------------------------------------------
*/

if (! function_exists('timeEntryActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function timeEntryActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'exportMonthly', 'manageAll', 'viewAll'] as $ability) {
            Permission::findOrCreate("time-entries.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("time-entries.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('timeEntryPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function timeEntryPayload(array $overrides = []): array
    {
        return [
            'date' => '2026-09-14',
            'title' => 'Chiamata cliente',
            'task_type_id' => TaskType::factory()->create()->id,
            'minutes' => 90,
            ...$overrides,
        ];
    }
}

// ---------------------------------------------------------------------------
// AC-001 — task_id overrides the submitted work_order_stage_id
// ---------------------------------------------------------------------------

it('AC-001: task_id in stage X is saved with stage X even when the payload submits stage Y', function () {
    $actor = timeEntryActorWith(['create']);
    $workOrder = WorkOrder::factory()->create();
    $stageX = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $stageY = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $task = Task::factory()->forCreator($actor)->create([
        'work_order_id' => $workOrder->id,
        'work_order_stage_id' => $stageX->id,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/time-entries', timeEntryPayload([
        'task_id' => $task->id,
        'work_order_stage_id' => $stageY->id,
    ]))->assertCreated();

    expect($response->json('data.work_order_stage.id'))->toBe($stageX->id)
        ->and($response->json('data.work_order_stage.name'))->toBe($stageX->name);

    $this->assertDatabaseHas('time_entries', [
        'id' => $response->json('data.id'),
        'work_order_stage_id' => $stageX->id,
    ]);
});

it('AC-001: task_id with no stage saves the entry with no stage even when the payload submits one', function () {
    $actor = timeEntryActorWith(['create']);
    $workOrder = WorkOrder::factory()->create();
    $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $task = Task::factory()->forCreator($actor)->create([
        'work_order_id' => $workOrder->id,
        'work_order_stage_id' => null,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/time-entries', timeEntryPayload([
        'task_id' => $task->id,
        'work_order_stage_id' => $stage->id,
    ]))->assertCreated();

    expect($response->json('data.work_order_stage'))->toBeNull();
    $this->assertDatabaseHas('time_entries', ['id' => $response->json('data.id'), 'work_order_stage_id' => null]);
});

// ---------------------------------------------------------------------------
// AC-002 — commessa, no task: open stage of THAT commessa only
// ---------------------------------------------------------------------------

it('AC-002: an open stage of the submitted work_order is accepted', function () {
    $actor = timeEntryActorWith(['create']);
    $workOrder = WorkOrder::factory()->create();
    $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/time-entries', timeEntryPayload([
        'work_order_id' => $workOrder->id,
        'work_order_stage_id' => $stage->id,
    ]))->assertCreated();

    expect($response->json('data.work_order_stage.id'))->toBe($stage->id);
});

it('AC-002: a closed stage of the submitted work_order is 422 on work_order_stage_id', function () {
    $actor = timeEntryActorWith(['create']);
    $workOrder = WorkOrder::factory()->create();
    $closedStage = WorkOrderStage::factory()->forWorkOrder($workOrder)->closed()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/time-entries', timeEntryPayload([
        'work_order_id' => $workOrder->id,
        'work_order_stage_id' => $closedStage->id,
    ]))->assertStatus(422)->assertJsonValidationErrors('work_order_stage_id');

    $this->assertDatabaseMissing('time_entries', ['work_order_id' => $workOrder->id]);
});

it('AC-002: a stage belonging to a different work_order is 422 on work_order_stage_id', function () {
    $actor = timeEntryActorWith(['create']);
    $workOrder = WorkOrder::factory()->create();
    $foreignStage = WorkOrderStage::factory()->create(); // its own, different work_order
    Sanctum::actingAs($actor);

    $this->postJson('/api/time-entries', timeEntryPayload([
        'work_order_id' => $workOrder->id,
        'work_order_stage_id' => $foreignStage->id,
    ]))->assertStatus(422)->assertJsonValidationErrors('work_order_stage_id');
});

// ---------------------------------------------------------------------------
// AC-003 — no commessa, stage submitted -> 422
// ---------------------------------------------------------------------------

it('AC-003: work_order_stage_id without work_order_id is 422 on work_order_stage_id', function () {
    $actor = timeEntryActorWith(['create']);
    $stage = WorkOrderStage::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/time-entries', timeEntryPayload(['work_order_stage_id' => $stage->id]))
        ->assertStatus(422)->assertJsonValidationErrors('work_order_stage_id');

    $this->assertDatabaseMissing('time_entries', ['user_id' => $actor->id]);
});

// ---------------------------------------------------------------------------
// AC-004 — RETIRED by spec 0167, D-2: "istantanea, mai risincronizzata" is
// no longer the rule. The task changing stage now DOES realign the voce —
// that requirement is covered by AC-001 in
// tests/Feature/TimeEntries/TimeEntryStageFollowsTaskTest.php, the opposite
// assertion of the one this test used to make.
// ---------------------------------------------------------------------------

it('D-2: updating a standalone voce without changing its stage is accepted even after the stage closed meanwhile', function () {
    $actor = timeEntryActorWith(['create', 'view', 'update']);
    $workOrder = WorkOrder::factory()->create();
    $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/time-entries', timeEntryPayload([
        'work_order_id' => $workOrder->id,
        'work_order_stage_id' => $stage->id,
    ]))->assertCreated();
    $entryId = $created->json('data.id');

    $stage->update(['closed_at' => now()]);

    $this->putJson("/api/time-entries/{$entryId}", timeEntryPayload([
        'minutes' => 45,
        'work_order_id' => $workOrder->id,
        'work_order_stage_id' => $stage->id,
    ]))->assertOk()->assertJsonPath('data.work_order_stage.id', $stage->id);
});

it('D-2: changing a standalone voce onto a now-closed different stage is 422', function () {
    $actor = timeEntryActorWith(['create', 'view', 'update']);
    $workOrder = WorkOrder::factory()->create();
    $openStage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();
    $closedStage = WorkOrderStage::factory()->forWorkOrder($workOrder)->closed()->create();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/time-entries', timeEntryPayload([
        'work_order_id' => $workOrder->id,
        'work_order_stage_id' => $openStage->id,
    ]))->assertCreated();
    $entryId = $created->json('data.id');

    $this->putJson("/api/time-entries/{$entryId}", timeEntryPayload([
        'work_order_id' => $workOrder->id,
        'work_order_stage_id' => $closedStage->id,
    ]))->assertStatus(422)->assertJsonValidationErrors('work_order_stage_id');
});
