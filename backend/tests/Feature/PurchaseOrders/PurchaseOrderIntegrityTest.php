<?php

declare(strict_types=1);

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\DataObjects\PurchaseOrders\PurchaseOrderData;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrders\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

// spec 0244 -- ODA-01: the company of an order is fixed after creation.

uses(RefreshDatabase::class);

it('AC-001: PUT with another company is a 422 on company_id and the order keeps company and number', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'update']));
    $order = purchaseOrderWithLines();
    $other = Company::factory()->create();
    $site = CompanySite::factory()->create(['company_id' => $other->id]);

    $this->putJson("/api/purchase-orders/{$order->id}", purchaseOrderUpdatePayload($order, [
        'company_id' => $other->id,
        'company_site_id' => $site->id,
    ]))->assertUnprocessable()->assertJsonValidationErrors('company_id');

    $fresh = $order->fresh();

    expect($fresh->company_id)->toBe($order->company_id)
        ->and($fresh->number)->toBe($order->number)
        ->and($fresh->company_site_id)->toBe($order->company_site_id);
});

it('AC-001: the service refuses the change even without the field permission, with the translated message', function () {
    $order = purchaseOrderWithLines();
    $payload = purchaseOrderUpdatePayload($order, ['company_id' => Company::factory()->create()->id]);
    $data = new PurchaseOrderData(
        header: ['company_id' => $payload['company_id'], 'order_date' => $payload['order_date']],
        lines: [],
        sectorIds: null,
        copies: 1,
    );

    try {
        app(PurchaseOrderService::class)->update($order, $data, purchaseOrderUserWith(['update']));
        $this->fail('Expected a validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['company_id' => [__('purchase_orders.company_locked')]]);
    }
});

it('AC-002: company_id is read-only on an existing order and editable on a new one', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'update']));
    $order = purchaseOrderWithLines();

    $this->getJson("/api/purchase-orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.field_permissions.company_id.editable', false)
        ->assertJsonPath('data.field_permissions.company_id.visible', true)
        ->assertJsonPath('data.field_permissions.supplier_id.editable', true);

    $permissions = app(ResourcePermissionsBuilder::class)->build(
        app(AuthorizationRegistry::class)->resolve('purchase-orders'),
        purchaseOrderUserWith(['create', 'update']),
        new PurchaseOrder,
    );

    expect($permissions['fields']['company_id']['editable'])->toBeTrue();
});
