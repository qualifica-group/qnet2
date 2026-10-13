<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// spec 0244 -- ODA-04 / ODA-06: the merge honours update/delete on the sources and the purchase invoices.

uses(RefreshDatabase::class);

/**
 * A destination (1 line) and a source (2 lines), same supplier and company.
 *
 * @return array{0: PurchaseOrder, 1: PurchaseOrder}
 */
function authorizationMergePair(): array
{
    $shared = ['supplier_id' => Registry::factory()->supplier()->create()->id, 'company_id' => Company::factory()->create()->id];

    return [purchaseOrderWithLines(attributes: $shared), purchaseOrderWithLines(lineCount: 2, attributes: $shared)];
}

it('AC-010: without delete, emptying a source is a 403 and nothing moves; with delete it succeeds', function () {
    [$destination, $source] = authorizationMergePair();
    $lineIds = $source->lines->pluck('id')->all();
    Sanctum::actingAs(purchaseOrderUserWith(['merge', 'update']));

    $this->postJson("/api/purchase-orders/{$destination->id}/merge", ['line_ids' => $lineIds])->assertForbidden();

    expect(PurchaseOrderLine::query()->where('purchase_order_id', $source->id)->count())->toBe(2)
        ->and(PurchaseOrder::query()->find($source->id))->not->toBeNull();

    Sanctum::actingAs(purchaseOrderUserWith(['merge', 'update', 'delete']));

    $this->postJson("/api/purchase-orders/{$destination->id}/merge", ['line_ids' => $lineIds])
        ->assertOk()
        ->assertJsonPath('meta.deleted_purchase_order_ids', [$source->id]);
});

it('AC-010: a partial merge needs no delete permission', function () {
    [$destination, $source] = authorizationMergePair();
    Sanctum::actingAs(purchaseOrderUserWith(['merge', 'update']));

    $this->postJson("/api/purchase-orders/{$destination->id}/merge", ['line_ids' => [$source->lines->first()->id]])->assertOk();
});

it('AC-011: without update on the source, even a partial merge is a 403 and nothing moves', function () {
    [$destination, $source] = authorizationMergePair();
    Sanctum::actingAs(purchaseOrderUserWith(['merge']));

    $this->postJson("/api/purchase-orders/{$destination->id}/merge", ['line_ids' => [$source->lines->first()->id]])->assertForbidden();

    expect(PurchaseOrderLine::query()->where('purchase_order_id', $source->id)->count())->toBe(2)
        ->and(PurchaseOrderLine::query()->where('purchase_order_id', $destination->id)->count())->toBe(1);
});

it('AC-012: emptying a source linked to a purchase invoice is a 409 and nothing moves', function () {
    [$destination, $source] = authorizationMergePair();
    purchaseDocument()->purchaseOrders()->attach($source->id);
    Sanctum::actingAs(purchaseOrderUserWith(['merge', 'update', 'delete']));

    $this->postJson("/api/purchase-orders/{$destination->id}/merge", ['line_ids' => $source->lines->pluck('id')->all()])
        ->assertStatus(409)
        ->assertJsonPath('message', __('purchase_orders.linked_to_purchase_invoice'));

    expect(PurchaseOrderLine::query()->where('purchase_order_id', $source->id)->count())->toBe(2);

    $this->postJson("/api/purchase-orders/{$destination->id}/merge", ['line_ids' => [$source->lines->first()->id]])->assertOk();
});
