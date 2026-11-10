<?php

use App\Models\BusinessFunction;
use App\Models\Task;
use App\Models\TaskTemplateItem;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflow;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * REQUIREMENT CHANGED (spec 0377, supersedes spec 0322 AC-009): a status in use
 * can no longer be deleted, alone or with its workflow.
 */
uses(RefreshDatabase::class);

if (! function_exists('workOrderStatusGuardActor')) {
    function workOrderStatusGuardActor(): User
    {
        $abilities = ['viewAny', 'view', 'update', 'delete'];

        foreach ($abilities as $ability) {
            Permission::findOrCreate("work-order-workflows.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(array_map(fn (string $ability): string => "work-order-workflows.{$ability}", $abilities));

        return $user;
    }
}

beforeEach(function (): void {
    $this->workflow = workOrderWorkflowFor('Set', ['business_function_id' => BusinessFunction::factory()->create()->id], ['Stato usato', 'Stato libero']);
    $this->used = $this->workflow->statuses->firstWhere('name', 'Stato usato');
    $this->unused = $this->workflow->statuses->firstWhere('name', 'Stato libero');
    $this->workOrderOn = function (WorkOrderWorkflowStatus $status): WorkOrder {
        $workOrder = WorkOrder::factory()->create();
        $workOrder->forceFill(['work_order_workflow_status_id' => $status->id])->save();

        return $workOrder;
    };
    Sanctum::actingAs(workOrderStatusGuardActor());
});

it('refuses to delete a workflow with a status in use, leaving everything as it was', function (): void {
    $workOrder = ($this->workOrderOn)($this->used);

    $this->deleteJson("/api/work-order-workflows/{$this->workflow->id}")
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Stato usato (1)'));

    expect($workOrder->fresh()->work_order_workflow_status_id)->toBe($this->used->id)
        ->and($this->workflow->fresh()->is_active)->toBeTrue();
});

it('keeps a workflow with a status in use out of a bulk delete', function (): void {
    ($this->workOrderOn)($this->used);

    $response = $this->postJson('/api/tables/work-order-workflows/bulk-delete', ['ids' => [$this->workflow->id]])->assertOk();

    expect($response->json('data.deleted'))->toBe(0)
        ->and(WorkOrderWorkflow::query()->find($this->workflow->id))->not->toBeNull();
});

it('counts as in use a status held only by another kind of record', function (string $holder): void {
    $status = $this->used;

    match ($holder) {
        'previous status' => tap(WorkOrder::factory()->create())->forceFill(['status_before_contract_termination_id' => $status->id])->save(),
        'task' => Task::factory()->create(['work_order_workflow_status_id' => $status->id]),
        'template row' => TaskTemplateItem::factory()->create(['work_order_workflow_status_id' => $status->id]),
    };

    $this->deleteJson("/api/work-order-workflows/{$this->workflow->id}")->assertStatus(409);

    expect($status->fresh())->not->toBeNull();
})->with(['previous status', 'task', 'template row']);

it('refuses a save that drops a status in use and writes nothing of it', function (): void {
    ($this->workOrderOn)($this->used);

    $this->patchJson("/api/work-order-workflows/{$this->workflow->id}", [
        'statuses' => [['id' => $this->unused->id, 'name' => 'Rinominato', 'group' => 'in_progress']],
    ])->assertStatus(409)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Stato usato (1)'));

    expect($this->used->fresh())->not->toBeNull()
        ->and($this->unused->fresh()->name)->toBe('Stato libero');
});

it('drops a custom status nobody uses', function (): void {
    $this->patchJson("/api/work-order-workflows/{$this->workflow->id}", [
        'statuses' => [['id' => $this->used->id, 'name' => 'Stato usato', 'group' => 'in_progress']],
    ])->assertOk();

    expect($this->unused->fresh())->toBeNull();
});

it('deletes a workflow with no status in use', function (): void {
    $this->deleteJson("/api/work-order-workflows/{$this->workflow->id}")->assertNoContent();

    expect(WorkOrderWorkflow::query()->find($this->workflow->id))->toBeNull();
});

it('refuses to drop a default status in use', function (): void {
    $default = WorkOrderWorkflowStatus::factory()->create(['work_order_workflow_id' => null, 'name' => 'Default usato']);
    ($this->workOrderOn)($default);

    $this->putJson('/api/work-order-workflows/default-statuses', [
        'statuses' => [['name' => 'Altro', 'group' => 'in_progress']],
    ])->assertStatus(409)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Default usato (1)'));

    expect($default->fresh())->not->toBeNull();
});
