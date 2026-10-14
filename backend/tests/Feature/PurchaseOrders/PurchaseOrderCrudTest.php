<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\CompanySite;
use App\Models\CostType;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Registry;
use App\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-001 / AC-002 / AC-003 — create
// ---------------------------------------------------------------------------

it('AC-001: creates a draft order with two lines and computes every amount server-side', function () {
    $actor = purchaseOrderUserWith(['view', 'create']);
    Sanctum::actingAs($actor);
    $payload = purchaseOrderPayload(['grand_total' => '999.99', 'created_by' => 12345, 'number' => 77, 'status' => 'approved'], 2);
    $payload['lines'][0]['taxable_amount'] = '999.00';
    $payload['lines'][0]['total_amount'] = '999.00';

    $response = $this->postJson('/api/purchase-orders', $payload)
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.number', 1)
        ->assertJsonPath('data.year', 2026)
        ->assertJsonPath('data.code', '1/2026')
        ->assertJsonPath('data.created_by.id', $actor->id)
        ->assertJsonPath('data.taxable_total', '40.04')
        ->assertJsonPath('data.vat_total', '8.80')
        ->assertJsonPath('data.grand_total', '48.84')
        ->assertJsonPath('data.lines.0.quantity', '2.000')
        ->assertJsonPath('data.lines.0.unit_price', '10.01')
        ->assertJsonPath('data.lines.0.taxable_amount', '20.02')
        ->assertJsonPath('data.lines.0.vat_amount', '4.40')
        ->assertJsonPath('data.lines.0.total_amount', '24.42')
        ->assertJsonPath('data.lines.0.purchase_request_line', null)
        ->assertJsonPath('data.lines.1.position', 2)
        ->assertJsonPath('data.merge_logs_count', 0)
        ->assertJsonPath('data.abilities.update', false)
        ->assertJsonPath('meta.created_ids', [PurchaseOrder::query()->value('id')])
        ->assertJsonStructure(['permissions', 'data' => [
            'id', 'number', 'year', 'code', 'status',
            'supplier' => ['id', 'name', 'email', 'is_qualified_supplier'],
            'company', 'company_site', 'operational_site', 'order_date', 'description', 'general_notes',
            'payment_method', 'is_internal_user', 'customer', 'work_order', 'quote', 'opportunity', 'sectors',
            'sector_notes', 'taxable_total', 'vat_total', 'grand_total', 'copy_group_id', 'created_by',
            'lines' => [['id', 'position', 'product', 'description', 'cost_type' => ['id', 'name'], 'unit_of_measure', 'quantity',
                'unit_price', 'vat_rate', 'taxable_amount', 'vat_amount', 'total_amount', 'work_order', 'purchase_request_line']],
            'merge_logs_count', 'created_at', 'updated_at',
            'abilities' => ['update', 'delete', 'merge', 'send', 'view_activity', 'transitions'],
            'field_permissions' => ['supplier_id' => ['visible', 'editable', 'required']],
        ]]);

    expect($response->json('data.order_date'))->toBe('2026-10-09');
    $order = PurchaseOrder::query()->first();
    expect(PurchaseOrder::query()->count())->toBe(1)
        ->and(PurchaseOrderLine::query()->count())->toBe(2)
        ->and($order->created_by)->toBe($actor->id)
        ->and($order->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($order->number)->toBe(1);
});

it('AC-001: forbids creating without the create permission', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view']));

    $this->postJson('/api/purchase-orders', purchaseOrderPayload())->assertForbidden();

    expect(PurchaseOrder::query()->count())->toBe(0);
});

it('AC-001: stores the optional header data and syncs the sectors', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'create']));
    $sectors = Sector::factory()->count(2)->create();
    $method = PaymentMethod::factory()->create();

    $this->postJson('/api/purchase-orders', purchaseOrderPayload([
        'payment_method_id' => $method->id,
        'is_internal_user' => true,
        'general_notes' => 'Deliver on site',
        'sector_ids' => $sectors->pluck('id')->all(),
        'sector_notes' => 'Specific',
    ]))
        ->assertCreated()
        ->assertJsonPath('data.payment_method.id', $method->id)
        ->assertJsonPath('data.is_internal_user', true)
        ->assertJsonPath('data.general_notes', 'Deliver on site')
        ->assertJsonPath('data.sector_notes', 'Specific')
        ->assertJsonCount(2, 'data.sectors');
});

it('AC-002: rejects an invalid payload with the keys of the contract and creates nothing', function (Closure $override, string $key) {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'create']));

    $this->postJson('/api/purchase-orders', $override(purchaseOrderPayload()))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$key]);

    expect(PurchaseOrder::query()->count())->toBe(0);
})->with([
    'no lines' => [fn (array $p): array => [...$p, 'lines' => []], 'lines'],
    'too many lines' => [fn (array $p): array => [...$p, 'lines' => array_fill(0, 201, $p['lines'][0])], 'lines'],
    'no cost type' => [function (array $p): array {
        unset($p['lines'][0]['cost_type_id']);

        return $p;
    }, 'lines.0.cost_type_id'],
    'inactive cost type' => [function (array $p): array {
        $p['lines'][0]['cost_type_id'] = CostType::factory()->create(['is_active' => false])->id;

        return $p;
    }, 'lines.0.cost_type_id'],
    'zero quantity' => [function (array $p): array {
        $p['lines'][0]['quantity'] = 0;

        return $p;
    }, 'lines.0.quantity'],
    'negative price' => [function (array $p): array {
        $p['lines'][0]['unit_price'] = -1;

        return $p;
    }, 'lines.0.unit_price'],
    'not a supplier' => [fn (array $p): array => [...$p, 'supplier_id' => Registry::factory()->create(['is_supplier' => false])->id], 'supplier_id'],
    'site of another company' => [fn (array $p): array => [...$p, 'company_site_id' => CompanySite::factory()->create()->id], 'company_site_id'],
    'unknown sector' => [fn (array $p): array => [...$p, 'sector_ids' => [987654]], 'sector_ids.0'],
    'no description' => [fn (array $p): array => [...$p, 'description' => ''], 'description'],
]);

it('AC-003: numbers are consecutive per company and year, and restart for another company or year', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'create']));
    $first = purchaseOrderPayload();
    $company = ['company_id' => $first['company_id'], 'company_site_id' => $first['company_site_id']];

    $this->postJson('/api/purchase-orders', $first)->assertCreated()->assertJsonPath('data.code', '1/2026');
    $this->postJson('/api/purchase-orders', purchaseOrderPayload($company))->assertCreated()->assertJsonPath('data.code', '2/2026');
    $this->postJson('/api/purchase-orders', purchaseOrderPayload($company + ['order_date' => '2027-01-15']))->assertCreated()->assertJsonPath('data.code', '1/2027');
    $this->postJson('/api/purchase-orders', purchaseOrderPayload())->assertCreated()->assertJsonPath('data.code', '1/2026');
});

it('AC-003: a deleted number is never reused', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'create', 'delete']));
    $first = purchaseOrderPayload();
    $company = ['company_id' => $first['company_id'], 'company_site_id' => $first['company_site_id']];

    $id = $this->postJson('/api/purchase-orders', $first)->assertCreated()->json('data.id');
    $this->deleteJson("/api/purchase-orders/{$id}")->assertOk();

    $this->postJson('/api/purchase-orders', purchaseOrderPayload($company))->assertCreated()->assertJsonPath('data.code', '2/2026');
});

// ---------------------------------------------------------------------------
// AC-004 — monthly copies
// ---------------------------------------------------------------------------

it('AC-004: copies = 3 creates three independent orders month by month, month end preserved', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'create']));

    $response = $this->postJson('/api/purchase-orders', purchaseOrderPayload(['order_date' => '2026-01-31', 'copies' => 3], 2))
        ->assertCreated()
        ->assertJsonPath('data.order_date', '2026-01-31')
        ->assertJsonCount(3, 'meta.created_ids');

    $orders = PurchaseOrder::query()->orderBy('id')->get();

    expect($response->json('meta.created_ids'))->toBe($orders->pluck('id')->all())
        ->and($orders->map(fn (PurchaseOrder $o): string => $o->order_date->toDateString())->all())->toBe(['2026-01-31', '2026-02-28', '2026-03-31'])
        ->and($orders->pluck('number')->all())->toBe([1, 2, 3])
        ->and($orders->pluck('copy_group_id')->unique()->count())->toBe(1)
        ->and($orders->first()->copy_group_id)->not->toBeNull()
        ->and($orders->every(fn (PurchaseOrder $o): bool => $o->lines()->count() === 2))->toBeTrue();
});

it('AC-004: a single order has no copy group and numbers follow the year of each copy date', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'create']));

    $this->postJson('/api/purchase-orders', purchaseOrderPayload())->assertCreated()->assertJsonPath('data.copy_group_id', null);

    $this->postJson('/api/purchase-orders', purchaseOrderPayload(['order_date' => '2026-12-15', 'copies' => 2]))->assertCreated();

    expect(PurchaseOrder::query()->orderBy('id')->pluck('year')->all())->toBe([2026, 2026, 2027]);
});

it('AC-004: copies outside 1..12 is a 422 and nothing is created', function (int $copies) {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'create']));

    $this->postJson('/api/purchase-orders', purchaseOrderPayload(['copies' => $copies]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['copies']);

    expect(PurchaseOrder::query()->count())->toBe(0);
})->with([0, 13]);

// ---------------------------------------------------------------------------
// AC-005 — update / delete
// ---------------------------------------------------------------------------

it('AC-005: a draft is updated: header, line edit, new line and deleted line', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'update']));
    $order = purchaseOrderWithLines(PurchaseOrderStatus::Draft, 2);
    [$kept, $dropped] = $order->lines;
    $costType = CostType::factory()->create();
    $payload = purchaseOrderUpdatePayload($order, [
        'description' => 'Renamed',
        'lines' => [
            ['id' => $kept->id, 'description' => 'Edited', 'cost_type_id' => $kept->cost_type_id, 'quantity' => '3', 'unit_price' => '50'],
            ['description' => 'Brand new', 'cost_type_id' => $costType->id, 'quantity' => '1', 'unit_price' => '25.50'],
        ],
    ]);

    $this->putJson("/api/purchase-orders/{$order->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.description', 'Renamed')
        ->assertJsonPath('data.code', $order->code())
        ->assertJsonPath('data.grand_total', '175.50')
        ->assertJsonCount(2, 'data.lines')
        ->assertJsonPath('data.lines.0.id', $kept->id)
        ->assertJsonPath('data.lines.0.description', 'Edited')
        ->assertJsonPath('data.lines.0.total_amount', '150.00')
        ->assertJsonPath('data.lines.1.description', 'Brand new')
        ->assertJsonPath('data.lines.1.position', 2);

    expect(PurchaseOrderLine::query()->whereKey($dropped->id)->exists())->toBeFalse();
});

it('AC-005: editing keeps the number even if the date changes', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'update']));
    $order = purchaseOrderWithLines();

    $this->putJson("/api/purchase-orders/{$order->id}", purchaseOrderUpdatePayload($order, ['order_date' => '2031-05-05']))->assertOk()->assertJsonPath('data.code', $order->code());
});

it('AC-005: PUT on an order that is not draft or in validation is a 409 and nothing changes', function (PurchaseOrderStatus $status) {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'update']));
    $order = purchaseOrderWithLines($status);

    $this->putJson("/api/purchase-orders/{$order->id}", purchaseOrderUpdatePayload($order, ['description' => 'Nope']))->assertStatus(409);

    expect($order->fresh()->description)->toBe($order->description);
})->with([PurchaseOrderStatus::Approved, PurchaseOrderStatus::SentToSupplier, PurchaseOrderStatus::Cancelled]);

it('AC-005: an order in validation can still be edited', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'update']));
    $order = purchaseOrderWithLines(PurchaseOrderStatus::InValidation);

    $this->putJson("/api/purchase-orders/{$order->id}", purchaseOrderUpdatePayload($order, ['description' => 'Still open']))->assertOk();
});

it('AC-005: PUT without the update permission is a 403, even on a locked order', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    $draft = purchaseOrderWithLines();
    $approved = purchaseOrderWithLines(PurchaseOrderStatus::Approved);

    $this->putJson("/api/purchase-orders/{$draft->id}", purchaseOrderUpdatePayload($draft))->assertForbidden();
    $this->putJson("/api/purchase-orders/{$approved->id}", purchaseOrderUpdatePayload($approved))->assertForbidden();
});

it('AC-005: a line id of another order is a 422 on lines.N.id and nothing is written', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'update']));
    $order = purchaseOrderWithLines();
    $foreign = purchaseOrderWithLines()->lines->first();
    $payload = purchaseOrderUpdatePayload($order, ['description' => 'Changed']);
    $payload['lines'][0]['id'] = $foreign->id;

    $this->putJson("/api/purchase-orders/{$order->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lines.0.id']);

    expect($order->fresh()->description)->toBe($order->description)
        ->and(PurchaseOrderLine::query()->where('purchase_order_id', $order->id)->count())->toBe(1);
});

it('AC-005: DELETE removes a draft with its lines and answers the envelope', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'delete']));
    $order = purchaseOrderWithLines(PurchaseOrderStatus::Draft, 2);

    $this->deleteJson("/api/purchase-orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'message']);

    expect(PurchaseOrder::query()->count())->toBe(0)->and(PurchaseOrderLine::query()->count())->toBe(0);
});

it('AC-005: DELETE on an approved order is a 409, without the permission a 403', function () {
    $approved = purchaseOrderWithLines(PurchaseOrderStatus::Approved);

    Sanctum::actingAs(purchaseOrderUserWith(['view', 'delete']));
    $this->deleteJson("/api/purchase-orders/{$approved->id}")->assertStatus(409);

    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    $this->deleteJson("/api/purchase-orders/{$approved->id}")->assertForbidden();

    expect(PurchaseOrder::query()->count())->toBe(1);
});

it('returns 404 for a missing order', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'update', 'delete']));

    $this->getJson('/api/purchase-orders/999999')->assertNotFound();
    $this->deleteJson('/api/purchase-orders/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// show: authorization and shape
// ---------------------------------------------------------------------------

it('shows the order with its abilities, and 403 without view', function () {
    $order = purchaseOrderWithLines();

    Sanctum::actingAs(purchaseOrderUserWith(['view', 'update', 'delete', 'merge', 'send', 'viewActivity']));
    $this->getJson("/api/purchase-orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.abilities.update', true)
        ->assertJsonPath('data.abilities.delete', true)
        ->assertJsonPath('data.abilities.merge', true)
        ->assertJsonPath('data.abilities.send', false)
        ->assertJsonPath('data.abilities.view_activity', true)
        ->assertJsonPath('data.field_permissions.supplier_id.editable', true);

    Sanctum::actingAs(purchaseOrderUserWith(['create']));
    $this->getJson("/api/purchase-orders/{$order->id}")->assertForbidden();
});

// Spec 0259 (REQUIREMENT CHANGED): past approval `update` holds for the service fields.
it('locks delete, merge and the supplier once the order is approved, keeping the service fields', function () {
    $order = purchaseOrderWithLines(PurchaseOrderStatus::Approved);
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'update', 'delete', 'merge', 'send']));

    $this->getJson("/api/purchase-orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.abilities.update', true)
        ->assertJsonPath('data.abilities.delete', false)
        ->assertJsonPath('data.abilities.merge', false)
        ->assertJsonPath('data.abilities.send', true)
        ->assertJsonPath('data.field_permissions.supplier_id.editable', false)
        ->assertJsonPath('data.field_permissions.general_notes.editable', true);
});

it('exposes the supplier primary email and qualification', function () {
    $supplier = Registry::factory()->qualifiedSupplier()->create();
    $supplier->personalData()->create(['type' => 'company', 'name' => 'ACME'])
        ->contacts()->createMany([
            ['type' => 'email', 'value' => 'second@acme.test', 'is_primary' => false],
            ['type' => 'email', 'value' => 'orders@acme.test', 'is_primary' => true],
        ]);
    $order = purchaseOrderWithLines(attributes: ['supplier_id' => $supplier->id]);
    Sanctum::actingAs(purchaseOrderUserWith(['view']));

    $this->getJson("/api/purchase-orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.supplier.email', 'orders@acme.test')
        ->assertJsonPath('data.supplier.is_qualified_supplier', true);
});
