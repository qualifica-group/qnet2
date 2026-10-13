<?php

use App\Models\Invoice;
use App\Models\ProformaRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function invoicedProformaRequest(): ProformaRequest
{
    $request = ProformaRequest::factory()->issued()->create();
    Invoice::factory()->create(['proforma_request_id' => $request->id]);

    return $request;
}

it('AC-001: an issued request with an invoice cannot be deleted (409) and still exists', function () {
    Sanctum::actingAs(proformaUserWith(['viewAny', 'view', 'delete']));
    $request = invoicedProformaRequest();

    $this->deleteJson("/api/proforma-requests/{$request->id}")
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('proforma_requests.delete_invoiced'));

    expect(ProformaRequest::query()->whereKey($request->id)->exists())->toBeTrue();
});

it('AC-001: an issued request without a linked invoice is refused as well', function () {
    Sanctum::actingAs(proformaUserWith(['delete']));
    $request = ProformaRequest::factory()->issued()->create();

    $this->deleteJson("/api/proforma-requests/{$request->id}")->assertStatus(409);
    expect(ProformaRequest::query()->whereKey($request->id)->exists())->toBeTrue();
});

it('AC-001: the generic bulk delete does not remove an invoiced request', function () {
    Sanctum::actingAs(proformaUserWith(['viewAny', 'view', 'delete']));
    $request = invoicedProformaRequest();

    $this->postJson('/api/tables/proforma-requests/bulk-delete', ['ids' => [$request->id]]);

    expect(ProformaRequest::query()->whereKey($request->id)->exists())->toBeTrue();
});

it('AC-002: a pending request without invoice is deleted', function () {
    Sanctum::actingAs(proformaUserWith(['delete']));
    $request = ProformaRequest::factory()->create();

    $this->deleteJson("/api/proforma-requests/{$request->id}")->assertNoContent();
    expect(ProformaRequest::query()->whereKey($request->id)->exists())->toBeFalse();
});

it('AC-002: without proforma-requests.delete the deletion is 403', function () {
    Sanctum::actingAs(proformaUserWith(['view']));
    $request = ProformaRequest::factory()->create();

    $this->deleteJson("/api/proforma-requests/{$request->id}")->assertForbidden();
});

it('AC-003: an issued request exposes no delete row action and permissions.actions.delete=false; a pending one keeps both', function () {
    Sanctum::actingAs(proformaUserWith(['viewAny', 'view', 'delete']));
    $issued = invoicedProformaRequest();
    $pending = ProformaRequest::factory()->create();

    $rows = collect($this->postJson('/api/tables/proforma-requests/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'))->keyBy('id');

    expect($rows[$issued->id]['actions'])->not->toContain('delete')
        ->and($rows[$pending->id]['actions'])->toContain('delete');

    $this->getJson("/api/proforma-requests/{$issued->id}")->assertOk()
        ->assertJsonPath('permissions.actions.delete', false);
    $this->getJson("/api/proforma-requests/{$pending->id}")->assertOk()
        ->assertJsonPath('permissions.actions.delete', true);
});

it('AC-004: deleting the invoice gives the request back as pending and deletable', function () {
    $user = invoiceUserWith(['view', 'delete']);
    $user->givePermissionTo('proforma-requests.delete');
    Sanctum::actingAs($user);
    $request = invoicedProformaRequest();
    $invoice = $request->invoice;

    $this->deleteJson("/api/proforma-requests/{$request->id}")->assertStatus(409);
    $this->deleteJson("/api/invoices/{$invoice->id}")->assertNoContent();
    expect($request->fresh()->status->value)->toBe('pending');

    $this->deleteJson("/api/proforma-requests/{$request->id}")->assertNoContent();
    expect(ProformaRequest::query()->whereKey($request->id)->exists())->toBeFalse();
});

it('AC-009: a work order whose requests are all issued cannot generate again (409 already_issued)', function () {
    Sanctum::actingAs(proformaUserWith(['create']));
    $workOrder = proformaWorkOrder([['consultancy']]);
    ProformaRequest::factory()->issued()->create(['work_order_id' => $workOrder->id]);

    $this->postJson("/api/work-orders/{$workOrder->id}/proforma-requests", ['note' => 'x'])
        ->assertStatus(409)
        ->assertJsonPath('message', __('proforma_requests.already_issued'));

    expect(ProformaRequest::query()->where('work_order_id', $workOrder->id)->count())->toBe(1);
});
