<?php

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
 * User directive 2026-11-23 (WorkOrderPolicy::manage): a Responsabile or
 * Partecipante who cannot edit the commessa still runs it: force close and
 * reopen, the task board and its "Fasi", the progress entries. Only the commessa form stays behind
 * `work-orders.update`. A reader who is not a member gets none of it.
 */

function memberManagementActor(): User
{
    Permission::findOrCreate('tasks.viewAny');
    $user = workOrderUserWith(['viewAny', 'view']);
    $user->givePermissionTo('tasks.viewAny');

    return $user;
}

it('lets a member without update force close and reopen the commessa, never edit its form', function () {
    $member = memberManagementActor();
    $workOrder = WorkOrder::factory()->create(['is_force_closed' => false]);
    $workOrder->participants()->attach($member, ['position' => 1]);
    Sanctum::actingAs($member);

    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.force_close', true)
        ->assertJsonPath('permissions.fields.title.editable', false);

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['is_force_closed' => true, 'force_close_reason' => 'Annullata dal cliente'])
        ->assertOk()
        ->assertJsonPath('permissions.actions.reopen', true);
    $this->patchJson("/api/work-orders/{$workOrder->id}", ['is_force_closed' => false, 'force_close_reason' => null])->assertOk();
    $this->patchJson("/api/work-orders/{$workOrder->id}", ['title' => 'Nuovo titolo'])->assertForbidden();
    $this->patchJson("/api/work-orders/{$workOrder->id}", ['is_force_closed' => true, 'force_close_reason' => 'x', 'title' => 'Nuovo titolo'])->assertForbidden();

    expect($workOrder->fresh())->is_force_closed->toBeFalse()->title->not->toBe('Nuovo titolo');
});

it('lets the Responsabile run the task board and its stages without update', function () {
    $supervisor = memberManagementActor();
    $workOrder = WorkOrder::factory()->create();
    $workOrder->supervisors()->attach($supervisor);
    Sanctum::actingAs($supervisor);

    $this->getJson("/api/work-orders/{$workOrder->id}/task-board")->assertOk()->assertJsonPath('data.is_read_only', false);
    $stageId = $this->postJson("/api/work-orders/{$workOrder->id}/stages", ['name' => 'Audit'])->assertCreated()->json('data.id');
    $this->patchJson("/api/work-orders/{$workOrder->id}/stages/{$stageId}", ['name' => 'Audit finale'])->assertOk();

    expect(WorkOrderStage::query()->find($stageId)->name)->toBe('Audit finale');
});

it('lets a Partecipante without update record the progress', function () {
    $member = memberManagementActor();
    $workOrder = WorkOrder::factory()->create();
    $workOrder->participants()->attach($member, ['position' => 1]);
    Sanctum::actingAs($member);

    $this->getJson("/api/work-orders/{$workOrder->id}")->assertOk()->assertJsonPath('permissions.actions.record_progress', true);
    $this->postJson("/api/work-orders/{$workOrder->id}/progress-entries", ['percentage' => 20, 'recorded_on' => '2026-11-23'])->assertCreated();
});

it('gives a reader who is not a member neither the closure nor the board', function () {
    $reader = memberManagementActor();
    $workOrder = WorkOrder::factory()->create(['is_force_closed' => false]);
    Sanctum::actingAs($reader);

    $this->getJson("/api/work-orders/{$workOrder->id}")->assertOk()->assertJsonPath('permissions.actions.force_close', false);
    $this->patchJson("/api/work-orders/{$workOrder->id}", ['is_force_closed' => true, 'force_close_reason' => 'x'])->assertForbidden();
    $this->getJson("/api/work-orders/{$workOrder->id}/task-board")->assertOk()->assertJsonPath('data.is_read_only', true);
    $this->postJson("/api/work-orders/{$workOrder->id}/stages", ['name' => 'Audit'])->assertForbidden();
    $this->getJson("/api/work-orders/{$workOrder->id}")->assertJsonPath('permissions.actions.record_progress', false);
    $this->postJson("/api/work-orders/{$workOrder->id}/progress-entries", ['percentage' => 20, 'recorded_on' => '2026-11-23'])->assertForbidden();
});
