<?php

use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0323, D-4: `work_order_workflow_status` is a read-only object in the
 * task detail (AC-016: never written through POST/PATCH).
 */
uses(RefreshDatabase::class);

it('shows the linked work order workflow status in the task detail and null when unlinked', function () {
    $actor = taskActorWith(['view']);
    $status = WorkOrderWorkflowStatus::factory()->create(['name' => 'Rilascio', 'color' => 'green', 'progress_percentage' => 60]);
    $linked = Task::factory()->forCreator($actor)->create();
    $linked->work_order_workflow_status_id = $status->id;
    $linked->save();
    $plain = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$linked->id}")->assertOk()
        ->assertJsonPath('data.work_order_workflow_status', [
            'id' => $status->id, 'name' => 'Rilascio', 'color' => 'green', 'progress_percentage' => 60,
        ]);

    $this->getJson("/api/tasks/{$plain->id}")->assertOk()
        ->assertJsonPath('data.work_order_workflow_status', null);
});

it('AC-016: POST and PATCH ignore work_order_workflow_status_id', function () {
    $actor = taskActorWith(['create', 'view', 'update']);
    $status = WorkOrderWorkflowStatus::factory()->create();
    Sanctum::actingAs($actor);

    $id = $this->postJson('/api/tasks', [
        'title' => 'Nuova', 'requester_id' => User::factory()->create()->id,
        'assignee_ids' => [User::factory()->create()->id], 'end_date' => '2026-12-31',
        'work_order_workflow_status_id' => $status->id,
    ])->assertCreated()->assertJsonPath('data.work_order_workflow_status', null)->json('data.id');

    expect(Task::find($id)->work_order_workflow_status_id)->toBeNull();

    $this->patchJson("/api/tasks/{$id}", ['title' => 'Rinominata', 'work_order_workflow_status_id' => $status->id])
        ->assertOk()->assertJsonPath('data.work_order_workflow_status', null);

    expect(Task::find($id))->title->toBe('Rinominata')
        ->work_order_workflow_status_id->toBeNull();
});
