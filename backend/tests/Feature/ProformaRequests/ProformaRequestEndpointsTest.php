<?php

use App\Models\PaymentMethod;
use App\Models\ProformaRequest;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-001: one consultancy request and one per distinct institution supplier, all pending and assigned to the actor', function () {
    $actor = proformaUserWith(['create']);
    Sanctum::actingAs($actor);
    $supplierA = Registry::factory()->create();
    $supplierB = Registry::factory()->create();
    $method = PaymentMethod::factory()->create();
    $workOrder = proformaWorkOrder([
        ['consultancy'], ['consultancy'],
        ['institution', $supplierA], ['institution', $supplierA], ['institution', $supplierB],
    ], $method);

    $data = $this->postJson("/api/work-orders/{$workOrder->id}/proforma-requests", ['note' => 'Please invoice'])
        ->assertCreated()->assertJsonPath('message', 'Created')->json('data');

    expect($data)->toHaveCount(3);
    $rows = ProformaRequest::query()->where('work_order_id', $workOrder->id)->get();
    expect($rows->pluck('kind')->map->value->sort()->values()->all())->toBe(['consultancy', 'institution', 'institution'])
        ->and($rows->filter(fn ($row) => $row->kind->value === 'institution')->pluck('supplier_id')->sort()->values()->all())
        ->toBe(collect([$supplierA->id, $supplierB->id])->sort()->values()->all())
        ->and($rows->pluck('status')->map->value->unique()->all())->toBe(['pending'])
        ->and($rows->pluck('assigned_to')->unique()->all())->toBe([$actor->id])
        ->and($rows->pluck('assigned_by')->unique()->all())->toBe([$actor->id])
        ->and($rows->pluck('payment_method_id')->unique()->all())->toBe([$method->id])
        ->and($rows->pluck('note')->unique()->all())->toBe(['Please invoice']);
});

it('AC-002: an institution without supplier makes one null-supplier request, untyped lines are ignored, no useful line is a 422', function () {
    Sanctum::actingAs(proformaUserWith(['create']));
    $workOrder = proformaWorkOrder([['institution'], ['institution'], [null]]);

    $this->postJson("/api/work-orders/{$workOrder->id}/proforma-requests", ['note' => 'x'])->assertCreated();
    $rows = ProformaRequest::query()->where('work_order_id', $workOrder->id)->get();
    expect($rows)->toHaveCount(1)->and($rows->first()->supplier_id)->toBeNull();

    $empty = proformaWorkOrder([[null]]);
    $this->postJson("/api/work-orders/{$empty->id}/proforma-requests", ['note' => 'x'])
        ->assertStatus(422)->assertJsonPath('success', false)->assertJsonPath('message', 'No billable lines on this work order.');
    expect(ProformaRequest::query()->where('work_order_id', $empty->id)->count())->toBe(0);
});

it('AC-003: a pending request makes a new submit a 409 with no record, a missing note is a 422', function () {
    Sanctum::actingAs(proformaUserWith(['create']));
    $workOrder = proformaWorkOrder([['consultancy']]);
    $url = "/api/work-orders/{$workOrder->id}/proforma-requests";

    $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('note');
    $this->postJson($url, ['note' => 'x'])->assertCreated();
    $this->postJson($url, ['note' => 'x'])->assertStatus(409)
        ->assertJsonPath('message', 'This work order already has a pending proforma request.');
    expect(ProformaRequest::query()->count())->toBe(1);

    $this->getJson("{$url}/summary")->assertOk()
        ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.work_order.id', $workOrder->id);
});

it('AC-004: create/summary need proforma-requests.create and a visible work order; show/update/delete need their ability', function () {
    $workOrder = proformaWorkOrder([['consultancy']]);
    $request = ProformaRequest::factory()->create(['work_order_id' => $workOrder->id]);
    $url = "/api/work-orders/{$workOrder->id}/proforma-requests";

    Sanctum::actingAs(proformaUserWith(['view']));
    $this->postJson($url, ['note' => 'x'])->assertForbidden();
    $this->getJson("{$url}/summary")->assertForbidden();
    $this->patchJson("/api/proforma-requests/{$request->id}", ['note' => 'x'])->assertForbidden();
    $this->deleteJson("/api/proforma-requests/{$request->id}")->assertForbidden();
    $this->getJson("/api/proforma-requests/{$request->id}")->assertOk();

    Sanctum::actingAs(proformaUserWith(['create'], seesWorkOrders: false));
    $this->getJson("{$url}/summary")->assertForbidden();
    $this->getJson("/api/proforma-requests/{$request->id}")->assertForbidden();
    $this->postJson('/api/tables/proforma-requests/rows', ['startRow' => 0, 'endRow' => 25])->assertForbidden();
});
