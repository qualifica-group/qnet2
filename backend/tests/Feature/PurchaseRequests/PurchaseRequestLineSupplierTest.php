<?php

use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

// ---------------------------------------------------------------------------
// AC-002 / AC-003 — supplier per line on create
// ---------------------------------------------------------------------------

it('AC-002: creates a request with lines of three different suppliers and no header supplier', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'create']));
    $suppliers = [supplierRegistry(), supplierRegistry(), supplierRegistry()];
    $payload = purchaseRequestPayload(['supplier_id' => $suppliers[0]->id], 3);

    foreach ($suppliers as $index => $supplier) {
        $payload['lines'][$index]['supplier_id'] = $supplier->id;
    }

    $response = $this->postJson('/api/purchase-requests', $payload)->assertCreated();

    expect($response->json('data'))->not->toHaveKey('supplier')
        ->and(array_column($response->json('data.lines'), 'supplier'))->toBe(
            array_map(fn ($supplier): array => ['id' => $supplier->id, 'name' => $supplier->name], $suppliers),
        );
});

it('AC-002: the supplier of a line is optional', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'create']));

    $this->postJson('/api/purchase-requests', purchaseRequestPayload())
        ->assertCreated()
        ->assertJsonPath('data.lines.0.supplier', null);
});

it('AC-003: refuses a registry that is not a supplier and stores nothing', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['create']));
    $payload = purchaseRequestPayload([], 2);
    $payload['lines'][0]['supplier_id'] = supplierRegistry()->id;
    $payload['lines'][1]['supplier_id'] = supplierRegistry(false)->id;

    $this->postJson('/api/purchase-requests', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lines.1.supplier_id')
        ->assertJsonMissingValidationErrors('lines.0.supplier_id');

    expect(PurchaseRequest::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-004 / AC-005 — supplier change on update
// ---------------------------------------------------------------------------

it('AC-004: a pending line takes a new supplier like the rest of its content', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update']));
    $request = purchaseRequestWithLines();
    $supplier = supplierRegistry();
    $payload = purchaseRequestUpdatePayload($request);
    $payload['lines'][0]['supplier_id'] = $supplier->id;

    $this->putJson("/api/purchase-requests/{$request->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.lines.0.supplier.id', $supplier->id);
});

it('AC-004: an approved line changes only its supplier, and only with fulfill or manage', function (string $ability, int $expected) {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update', $ability]));
    $request = purchaseRequestWithLines([LineStatus::Approved], [], ['supplier_id' => supplierRegistry()->id]);
    $next = supplierRegistry();
    $payload = purchaseRequestUpdatePayload($request);
    $payload['lines'][0]['supplier_id'] = $next->id;

    $response = $this->putJson("/api/purchase-requests/{$request->id}", $payload)->assertStatus($expected);
    $expected === 200
        ? $response->assertJsonPath('data.lines.0.supplier.id', $next->id)
        : $response->assertJsonValidationErrors('lines.0');
})->with([
    'fulfill' => ['fulfill', 200],
    'manageStatuses' => ['manageStatuses', 200],
    'close only' => ['close', 422],
]);

it('AC-004: changing the quantity of an approved line is refused even with fulfill', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update', 'fulfill']));
    $request = purchaseRequestWithLines([LineStatus::Approved]);
    $payload = purchaseRequestUpdatePayload($request);
    $payload['lines'][0]['supplier_id'] = supplierRegistry()->id;
    $payload['lines'][0]['quantity'] = '9';

    $this->putJson("/api/purchase-requests/{$request->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lines.0');
    expect($request->lines->first()->fresh()->supplier_id)->toBeNull();
});

it('AC-005: the supplier of an ordered line is frozen, even with fulfill and manage', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update', 'fulfill', 'manageStatuses']));
    $request = purchaseRequestWithLines([LineStatus::Ordered], [], ['supplier_id' => supplierRegistry()->id]);
    $payload = purchaseRequestUpdatePayload($request);
    $payload['lines'][0]['supplier_id'] = supplierRegistry()->id;

    $this->putJson("/api/purchase-requests/{$request->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lines.0');

    // An unchanged supplier on a frozen line is accepted.
    $this->putJson("/api/purchase-requests/{$request->id}", purchaseRequestUpdatePayload($request))->assertOk();
});

// ---------------------------------------------------------------------------
// AC-006 — abilities.update_supplier, Resource and grid rows
// ---------------------------------------------------------------------------

it('AC-006: update_supplier follows the supplier rule, identically in the Resource and in the lines grid', function (array $abilities, LineStatus $status, bool $closed, bool $expected) {
    $actor = purchaseRequestUserWith(['view', 'viewAll', ...$abilities]);
    $request = purchaseRequestWithLines([$status], $closed ? ['status' => 'closed', 'closed_by' => $actor->id, 'closed_at' => now()] : []);
    Sanctum::actingAs($actor);

    $fromResource = $this->getJson("/api/purchase-requests/{$request->id}")->assertOk()->json('data.lines.0.abilities.update_supplier');
    $rows = $this->postJson('/api/tables/purchase-request-lines/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items');
    $fromGrid = collect($rows)->firstWhere('id', $request->lines->first()->id)['abilities']['update_supplier'];

    expect($fromResource)->toBe($expected)->and($fromGrid)->toBe($expected);
})->with([
    'pending with update' => [['update'], LineStatus::PendingApproval, false, true],
    'pending without update' => [[], LineStatus::PendingApproval, false, false],
    'approved with fulfill' => [['fulfill'], LineStatus::Approved, false, true],
    'approved with manage' => [['manageStatuses'], LineStatus::Approved, false, true],
    'approved without capability' => [['update'], LineStatus::Approved, false, false],
    'ordered with manage' => [['update', 'fulfill', 'manageStatuses'], LineStatus::Ordered, false, false],
    'received with manage' => [['update', 'fulfill', 'manageStatuses'], LineStatus::Received, false, false],
    'rejected with manage' => [['update', 'fulfill', 'manageStatuses'], LineStatus::Rejected, false, false],
    'on hold with manage' => [['update', 'fulfill', 'manageStatuses'], LineStatus::OnHold, false, false],
    'closed request' => [['update', 'fulfill', 'manageStatuses'], LineStatus::Approved, true, false],
]);

it('AC-006: the lines grid row carries the supplier', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    $supplier = supplierRegistry();
    purchaseRequestWithLines([LineStatus::Approved], [], ['supplier_id' => $supplier->id]);

    $rows = $this->postJson('/api/tables/purchase-request-lines/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items');

    expect($rows[0]['supplier'])->toBe(['id' => $supplier->id, 'name' => $supplier->name])
        ->and(PurchaseRequestLine::query()->count())->toBe(1);
});
