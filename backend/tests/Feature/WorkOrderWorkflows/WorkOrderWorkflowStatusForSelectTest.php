<?php

use App\Models\User;
use App\Models\WorkOrderWorkflow;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// GET /api/work-order-workflow-statuses/for-select (spec 0323, D-5, ADR 0011). AC-003.

// The migration seeds the default set: start from a clean table so the order is asserted on known rows.
beforeEach(fn () => WorkOrderWorkflowStatus::query()->delete());

function workflowStatusSelectNames(TestResponse $response): array
{
    return collect($response->json('data.items') ?? $response->json('items'))->pluck('name')->all();
}

it('orders default set first, then workflows by name, then sort_order (AC-003)', function () {
    $zeta = WorkOrderWorkflow::factory()->create(['name' => 'Zeta']);
    $alfa = WorkOrderWorkflow::factory()->create(['name' => 'Alfa']);
    WorkOrderWorkflowStatus::factory()->create(['work_order_workflow_id' => $zeta->id, 'name' => 'Z1', 'sort_order' => 1]);
    WorkOrderWorkflowStatus::factory()->create(['work_order_workflow_id' => $alfa->id, 'name' => 'A2', 'sort_order' => 2]);
    WorkOrderWorkflowStatus::factory()->create(['work_order_workflow_id' => $alfa->id, 'name' => 'A1', 'sort_order' => 1]);
    WorkOrderWorkflowStatus::factory()->global()->create(['name' => 'D1', 'sort_order' => 1]);
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/work-order-workflow-statuses/for-select')->assertOk();

    expect(workflowStatusSelectNames($response))->toBe(['D1', 'A1', 'A2', 'Z1']);
    $response->assertJsonPath('items.0.work_order_workflow_id', null)
        ->assertJsonPath('items.0.work_order_workflow_name', null)
        ->assertJsonPath('items.1.work_order_workflow_name', 'Alfa')
        ->assertJsonStructure(['items' => [['id', 'name', 'color', 'progress_percentage', 'system_key', 'work_order_workflow_id', 'work_order_workflow_name']], 'pagination']);
});

it('searches on status name or workflow name (AC-003)', function () {
    $bandi = WorkOrderWorkflow::factory()->create(['name' => 'Bandi']);
    WorkOrderWorkflowStatus::factory()->create(['work_order_workflow_id' => $bandi->id, 'name' => 'Istruttoria']);
    WorkOrderWorkflowStatus::factory()->global()->create(['name' => 'Collaudo']);
    Sanctum::actingAs(User::factory()->create());

    expect(workflowStatusSelectNames($this->getJson('/api/work-order-workflow-statuses/for-select?search=Collaudo')))->toBe(['Collaudo']);
    expect(workflowStatusSelectNames($this->getJson('/api/work-order-workflow-statuses/for-select?search=Bandi')))->toBe(['Istruttoria']);
});

it('hides inactive workflows unless requested via ids[] (AC-003)', function () {
    $inactive = WorkOrderWorkflow::factory()->inactive()->create(['name' => 'Vecchio']);
    $status = WorkOrderWorkflowStatus::factory()->create(['work_order_workflow_id' => $inactive->id, 'name' => 'Spento']);
    WorkOrderWorkflowStatus::factory()->global()->create(['name' => 'Base']);
    Sanctum::actingAs(User::factory()->create());

    expect(workflowStatusSelectNames($this->getJson('/api/work-order-workflow-statuses/for-select')))->toBe(['Base']);

    $hydrated = $this->getJson('/api/work-order-workflow-statuses/for-select?ids[]='.$status->id)->assertOk();
    expect(workflowStatusSelectNames($hydrated))->toBe(['Base', 'Spento']);
    $hydrated->assertJsonPath('pagination.total', 1);
});

it('paginates with offset and limit (AC-003)', function () {
    WorkOrderWorkflowStatus::factory()->global()->count(3)->create();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/work-order-workflow-statuses/for-select?limit=2&offset=2')
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('pagination.total', 3);
});

it('rejects an invalid query with 422 (AC-003)', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/work-order-workflow-statuses/for-select?limit=0')->assertUnprocessable();
});

it('requires authentication (AC-003)', function () {
    $this->getJson('/api/work-order-workflow-statuses/for-select')->assertUnauthorized();
});
