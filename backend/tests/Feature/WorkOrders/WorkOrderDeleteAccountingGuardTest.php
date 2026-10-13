<?php

use App\Models\Invoice;
use App\Models\ProformaRequest;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Task;
use App\Models\WorkOrder;
use App\Models\WorkOrderCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
 * Spec 0243, D-1: a commessa with accounting documents cannot be deleted
 * (409), nor offered the `delete` action; bulk delete respects the guard.
 */

/** One accounting document of each kind, attached to $workOrder. */
dataset('accounting documents', [
    'proforma request' => fn (WorkOrder $wo) => ProformaRequest::factory()->create(['work_order_id' => $wo->id]),
    'active invoice' => fn (WorkOrder $wo) => Invoice::factory()->create(['work_order_id' => $wo->id]),
    'purchase order header' => fn (WorkOrder $wo) => PurchaseOrder::factory()->create(['work_order_id' => $wo->id]),
    'purchase order line' => fn (WorkOrder $wo) => PurchaseOrderLine::factory()->create(['work_order_id' => $wo->id]),
    'purchase invoice' => fn (WorkOrder $wo) => PurchaseInvoice::factory()->create(['work_order_id' => $wo->id]),
]);

it('refuses to delete a commessa with accounting documents (AC-005, AC-006)', function (Closure $attach) {
    Sanctum::actingAs(workOrderUserWith(['delete']));
    $workOrder = WorkOrder::factory()->create();
    $attach($workOrder);

    $this->deleteJson("/api/work-orders/{$workOrder->id}")
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('work_orders.delete_has_accounting_documents'));

    $this->assertDatabaseHas('work_orders', ['id' => $workOrder->id]);
})->with('accounting documents');

it('keeps the proforma requests of a refused commessa (AC-005)', function () {
    Sanctum::actingAs(workOrderUserWith(['delete']));
    $workOrder = WorkOrder::factory()->create();
    $request = ProformaRequest::factory()->create(['work_order_id' => $workOrder->id]);

    $this->deleteJson("/api/work-orders/{$workOrder->id}")->assertStatus(409);

    $this->assertDatabaseHas('proforma_requests', ['id' => $request->id]);
});

it('deletes a commessa that only has tasks, costs and hours (AC-007)', function () {
    Sanctum::actingAs(workOrderUserWith(['delete']));
    $workOrder = WorkOrder::factory()->create();
    Task::factory()->create(['work_order_id' => $workOrder->id]);
    WorkOrderCost::factory()->create(['work_order_id' => $workOrder->id]);

    $this->deleteJson("/api/work-orders/{$workOrder->id}")->assertNoContent();

    $this->assertDatabaseMissing('work_orders', ['id' => $workOrder->id]);
});

it('does not offer delete on the grid row of a commessa with documents (AC-008)', function (Closure $attach) {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view', 'delete']));
    $blocked = WorkOrder::factory()->create(['title' => 'Bloccata']);
    WorkOrder::factory()->create(['title' => 'Libera']);
    $attach($blocked);

    $items = collect($this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('title', 'Bloccata')['actions'])->not->toContain('delete')
        ->and($items->firstWhere('title', 'Libera')['actions'])->toContain('delete');
})->with('accounting documents');

it('reports permissions.actions.delete=false in the detail of a commessa with documents (AC-008)', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view', 'delete']));
    $blocked = WorkOrder::factory()->create();
    $free = WorkOrder::factory()->create();
    Invoice::factory()->create(['work_order_id' => $blocked->id]);

    $this->getJson("/api/work-orders/{$blocked->id}")->assertOk()->assertJsonPath('permissions.actions.delete', false);
    $this->getJson("/api/work-orders/{$free->id}")->assertOk()->assertJsonPath('permissions.actions.delete', true);
});

it('does not delete a blocked commessa through bulk delete (AC-008)', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'delete']));
    $blocked = WorkOrder::factory()->create();
    $free = WorkOrder::factory()->create();
    PurchaseInvoice::factory()->create(['work_order_id' => $blocked->id]);

    $response = $this->postJson('/api/tables/work-orders/bulk-delete', ['ids' => [$blocked->id, $free->id]])->assertOk();

    expect($response->json('data.deleted'))->toBe(1)
        ->and(collect($response->json('data.failed'))->firstWhere('id', $blocked->id)['reason'])->toBe('guarded');
    $this->assertDatabaseHas('work_orders', ['id' => $blocked->id]);
    $this->assertDatabaseMissing('work_orders', ['id' => $free->id]);
});

it('returns 403 without work-orders.delete even on a free commessa', function () {
    Sanctum::actingAs(workOrderUserWith(['view']));
    $workOrder = WorkOrder::factory()->create();

    $this->deleteJson("/api/work-orders/{$workOrder->id}")->assertForbidden();
});
