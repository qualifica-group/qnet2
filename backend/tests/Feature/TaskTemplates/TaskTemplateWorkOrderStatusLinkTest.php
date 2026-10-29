<?php

use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use App\Models\WorkOrderWorkflow;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Template row <-> work order workflow status link (spec 0323, D-4/D-5). AC-002.

function templateLinkUser(): User
{
    foreach (['create', 'update', 'view', 'viewAny'] as $ability) {
        Permission::findOrCreate("task-templates.{$ability}");
    }

    return User::factory()->create()->givePermissionTo(
        'task-templates.create', 'task-templates.update', 'task-templates.view', 'task-templates.viewAny',
    );
}

it('stores the linked status on create and returns it with the workflow name (AC-002)', function () {
    $workflow = WorkOrderWorkflow::factory()->create(['name' => 'Bandi']);
    $status = WorkOrderWorkflowStatus::factory()->create([
        'work_order_workflow_id' => $workflow->id, 'name' => 'Istruttoria', 'progress_percentage' => 40,
    ]);
    Sanctum::actingAs(templateLinkUser());

    $response = $this->postJson('/api/task-templates', [
        'name' => 'Con stato',
        'items' => [
            ['title' => 'Riga collegata', 'due_offset_days' => 0, 'work_order_workflow_status_id' => $status->id],
            ['title' => 'Riga libera', 'due_offset_days' => 1],
        ],
    ])->assertCreated();

    $response->assertJsonPath('data.items.0.work_order_workflow_status_id', $status->id)
        ->assertJsonPath('data.items.0.work_order_workflow_status.name', 'Istruttoria')
        ->assertJsonPath('data.items.0.work_order_workflow_status.progress_percentage', 40)
        ->assertJsonPath('data.items.0.work_order_workflow_status.work_order_workflow_id', $workflow->id)
        ->assertJsonPath('data.items.0.work_order_workflow_status.work_order_workflow_name', 'Bandi')
        ->assertJsonPath('data.items.1.work_order_workflow_status_id', null)
        ->assertJsonPath('data.items.1.work_order_workflow_status', null);

    $this->getJson('/api/task-templates/'.$response->json('data.id'))
        ->assertOk()
        ->assertJsonPath('data.items.0.work_order_workflow_status.work_order_workflow_name', 'Bandi');
});

it('a default-set status has a null workflow name (AC-002)', function () {
    $status = WorkOrderWorkflowStatus::factory()->global()->create();
    Sanctum::actingAs(templateLinkUser());

    $this->postJson('/api/task-templates', [
        'name' => 'Set predefinito',
        'items' => [['title' => 'R', 'due_offset_days' => 0, 'work_order_workflow_status_id' => $status->id]],
    ])->assertCreated()
        ->assertJsonPath('data.items.0.work_order_workflow_status.work_order_workflow_id', null)
        ->assertJsonPath('data.items.0.work_order_workflow_status.work_order_workflow_name', null);
});

it('update sets and clears the link (AC-002)', function () {
    $status = WorkOrderWorkflowStatus::factory()->create();
    $template = TaskTemplate::factory()->create();
    $row = TaskTemplateItem::factory()->forTemplate($template)->create();
    Sanctum::actingAs(templateLinkUser());

    $payload = fn (?int $statusId): array => ['items' => [[
        'id' => $row->id, 'title' => $row->title, 'due_offset_days' => 0, 'work_order_workflow_status_id' => $statusId,
    ]]];

    $this->putJson("/api/task-templates/{$template->id}", $payload($status->id))
        ->assertOk()
        ->assertJsonPath('data.items.0.work_order_workflow_status_id', $status->id);

    $this->putJson("/api/task-templates/{$template->id}", $payload(null))
        ->assertOk()
        ->assertJsonPath('data.items.0.work_order_workflow_status_id', null);

    expect($row->fresh()->work_order_workflow_status_id)->toBeNull();
});

it('rejects a non-existent status with 422 on the row field (AC-002)', function () {
    Sanctum::actingAs(templateLinkUser());

    $this->postJson('/api/task-templates', [
        'name' => 'Stato fantasma',
        'items' => [['title' => 'R', 'due_offset_days' => 0, 'work_order_workflow_status_id' => 999999]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['items.0.work_order_workflow_status_id']);
});

it('requires authentication on create (AC-002)', function () {
    $this->postJson('/api/task-templates', [])->assertUnauthorized();
});
