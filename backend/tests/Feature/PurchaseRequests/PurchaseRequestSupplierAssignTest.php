<?php

use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Models\PurchaseRequestLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function assignSupplier(object $test, array $lineIds, mixed $supplierId): TestResponse
{
    return $test->postJson('/api/purchase-request-lines/supplier', ['line_ids' => $lineIds, 'supplier_id' => $supplierId]);
}

function lineSuppliers(array $lineIds): array
{
    return PurchaseRequestLine::query()->whereIn('id', $lineIds)->orderBy('id')->pluck('supplier_id')->all();
}

// ---------------------------------------------------------------------------
// AC-007 / AC-008 — supplier required to order
// ---------------------------------------------------------------------------

it('AC-007: ordering a line without supplier is refused and nothing changes', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['fulfill']));
    $supplier = supplierRegistry();
    $request = purchaseRequestWithLines([LineStatus::Approved, LineStatus::Approved]);
    $request->lines[0]->update(['supplier_id' => $supplier->id]);
    $ids = $request->lines->pluck('id')->all();

    changeStatus($this, $ids, 'ordered')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('line_ids.1')
        ->assertJsonMissingValidationErrors('line_ids.0');

    expect($request->lines()->where('status', LineStatus::Approved->value)->count())->toBe(2);
});

it('AC-007: the supplier is required even with the manage capability, and not for other statuses', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['manageStatuses']));
    $request = purchaseRequestWithLines([LineStatus::Approved, LineStatus::Approved]);

    changeStatus($this, [$request->lines[0]->id], 'ordered')->assertUnprocessable()->assertJsonValidationErrors('line_ids.0');
    changeStatus($this, [$request->lines[0]->id], 'on_hold')->assertOk();
});

it('AC-008: the supplied suppliers are saved with the change to ordered', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['fulfill']));
    $existing = supplierRegistry();
    $supplied = supplierRegistry();
    $request = purchaseRequestWithLines([LineStatus::Approved, LineStatus::Approved]);
    $request->lines[0]->update(['supplier_id' => $existing->id]);
    $ids = $request->lines->pluck('id')->all();

    changeStatus($this, $ids, 'ordered', ['suppliers' => [['line_id' => $ids[1], 'supplier_id' => $supplied->id]]])
        ->assertOk()
        ->assertJsonPath('data.updated_count', 2);

    expect(lineSuppliers($ids))->toBe([$existing->id, $supplied->id])
        ->and($request->lines()->where('status', LineStatus::Ordered->value)->count())->toBe(2);
});

it('AC-008: suppliers are refused with another status, for a foreign line or a non supplier', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['fulfill']));
    $supplier = supplierRegistry();
    $request = purchaseRequestWithLines([LineStatus::Approved, LineStatus::Approved]);
    [$first, $second] = $request->lines->pluck('id')->all();

    changeStatus($this, [$first], 'on_hold', ['suppliers' => [['line_id' => $first, 'supplier_id' => $supplier->id]]])
        ->assertUnprocessable()->assertJsonValidationErrors('suppliers');
    changeStatus($this, [$first], 'ordered', ['suppliers' => [['line_id' => $second, 'supplier_id' => $supplier->id]]])
        ->assertUnprocessable()->assertJsonValidationErrors('suppliers.0.line_id');
    changeStatus($this, [$first], 'ordered', ['suppliers' => [['line_id' => $first, 'supplier_id' => supplierRegistry(false)->id]]])
        ->assertUnprocessable()->assertJsonValidationErrors('suppliers.0.supplier_id');

    expect(lineSuppliers([$first, $second]))->toBe([null, null]);
});

// ---------------------------------------------------------------------------
// AC-009 — mass supplier assignment
// ---------------------------------------------------------------------------

it('AC-009: assigns one supplier to the selected approved lines', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['fulfill']));
    $supplier = supplierRegistry();
    $first = purchaseRequestWithLines([LineStatus::Approved]);
    $second = purchaseRequestWithLines([LineStatus::Approved]);
    $ids = [$first->lines[0]->id, $second->lines[0]->id];

    assignSupplier($this, $ids, $supplier->id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.updated_count', 2);

    expect(lineSuppliers($ids))->toBe([$supplier->id, $supplier->id]);
});

it('AC-009: a frozen line refuses the whole assignment', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['fulfill']));
    $request = purchaseRequestWithLines([LineStatus::Approved, LineStatus::Ordered], [], ['supplier_id' => supplierRegistry()->id]);
    $ids = $request->lines->pluck('id')->all();
    $before = lineSuppliers($ids);

    assignSupplier($this, $ids, supplierRegistry()->id)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('line_ids.1')
        ->assertJsonMissingValidationErrors('line_ids.0');

    expect(lineSuppliers($ids))->toBe($before);
});

it('AC-009: pending lines are assigned by who can update the request, not by a bare capability holder', function () {
    $request = purchaseRequestWithLines([LineStatus::PendingApproval]);
    $id = $request->lines[0]->id;
    $supplier = supplierRegistry();

    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'fulfill']));
    assignSupplier($this, [$id], $supplier->id)->assertUnprocessable()->assertJsonValidationErrors('line_ids.0');

    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update']));
    assignSupplier($this, [$id], $supplier->id)->assertOk();
    expect(lineSuppliers([$id]))->toBe([$supplier->id]);
});

it('AC-009: without any capability or update right the assignment is forbidden', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    $request = purchaseRequestWithLines([LineStatus::Approved]);

    assignSupplier($this, [$request->lines[0]->id], supplierRegistry()->id)->assertForbidden();
    expect(lineSuppliers([$request->lines[0]->id]))->toBe([null]);
});

it('AC-009: a closed request answers 409', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['fulfill']));
    $request = purchaseRequestWithLines([LineStatus::Approved], ['status' => 'closed', 'closed_by' => User::factory()->create()->id, 'closed_at' => now()]);

    assignSupplier($this, [$request->lines[0]->id], supplierRegistry()->id)->assertStatus(409);
});

it('AC-009: validates the payload', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['fulfill']));
    $request = purchaseRequestWithLines([LineStatus::Approved]);
    $id = $request->lines[0]->id;

    assignSupplier($this, [$id], supplierRegistry(false)->id)->assertUnprocessable()->assertJsonValidationErrors('supplier_id');
    assignSupplier($this, [$id], null)->assertUnprocessable()->assertJsonValidationErrors('supplier_id');
    assignSupplier($this, [], supplierRegistry()->id)->assertUnprocessable()->assertJsonValidationErrors('line_ids');
    assignSupplier($this, [$id, $id], supplierRegistry()->id)->assertUnprocessable();
    assignSupplier($this, [999999], supplierRegistry()->id)->assertUnprocessable()->assertJsonValidationErrors('line_ids.0');
});

it('AC-009: requires authentication', function () {
    $this->postJson('/api/purchase-request-lines/supplier', [])->assertUnauthorized();
});
