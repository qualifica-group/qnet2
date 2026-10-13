<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseInvoice;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(purchaseInvoiceUserWith(['viewAny', 'view', 'create', 'update', 'delete']));
});

it('AC-003: billing order lines links their orders in the pivot and keeps the order line reference', function () {
    $payload = purchaseInvoicePayload();
    $first = invoiceableOrderOf($payload);
    $second = invoiceableOrderOf($payload, PurchaseOrderStatus::Fulfilled);

    $response = $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [
        orderLinePayload($first->lines[0]),
        orderLinePayload($first->lines[1]),
        orderLinePayload($second->lines[0]),
    ]])->assertCreated();

    $document = PurchaseInvoice::query()->findOrFail($response->json('data.id'));

    expect($document->purchaseOrders()->pluck('purchase_orders.id')->all())->toEqualCanonicalizing([$first->id, $second->id])
        ->and(collect($response->json('data.purchase_orders'))->pluck('code')->all())->toEqualCanonicalizing([$first->code(), $second->code()])
        ->and($response->json('data.lines.0.purchase_order_line_id'))->toBe($first->lines[0]->id)
        ->and($response->json('data.lines.0.purchase_order.code'))->toBe($first->code());
});

it('AC-003: an explicit order id links the order even without its lines', function () {
    $payload = purchaseInvoicePayload();
    $order = invoiceableOrderOf($payload, PurchaseOrderStatus::SentToSupplier);

    $response = $this->postJson('/api/purchase-invoices', [...$payload, 'purchase_order_ids' => [$order->id]])->assertCreated();

    expect(PurchaseInvoice::query()->findOrFail($response->json('data.id'))->purchaseOrders()->count())->toBe(1);
});

it('AC-003: orders of another supplier or company, or not invoiceable, are refused', function () {
    $payload = purchaseInvoicePayload();
    $other = invoiceableOrderOf(purchaseInvoicePayload());
    $draft = invoiceableOrderOf($payload, PurchaseOrderStatus::Draft);
    $cancelled = invoiceableOrderOf($payload, PurchaseOrderStatus::Cancelled);

    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [orderLinePayload($other->lines[0])]])
        ->assertUnprocessable()->assertJsonValidationErrors('lines.0.purchase_order_line_id');
    $this->postJson('/api/purchase-invoices', [...$payload, 'purchase_order_ids' => [$other->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('purchase_order_ids');
    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [orderLinePayload($draft->lines[0])]])
        ->assertUnprocessable()->assertJsonValidationErrors('lines.0.purchase_order_line_id');
    $this->postJson('/api/purchase-invoices', [...$payload, 'purchase_order_ids' => [$cancelled->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('purchase_order_ids');
    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [[...orderLinePayload($draft->lines[0]), 'purchase_order_line_id' => 999999]]])
        ->assertUnprocessable()->assertJsonValidationErrors('lines.0.purchase_order_line_id');

    expect(PurchaseInvoice::query()->count())->toBe(0);
});

it('AC-004: an order line already on a document is refused on another one, and on the same line twice', function () {
    $payload = purchaseInvoicePayload();
    $order = invoiceableOrderOf($payload);

    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [orderLinePayload($order->lines[0])]])->assertCreated();

    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [orderLinePayload($order->lines[0])]])
        ->assertUnprocessable()->assertJsonValidationErrors('lines.0.purchase_order_line_id');
    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [orderLinePayload($order->lines[1]), orderLinePayload($order->lines[1])]])
        ->assertUnprocessable()->assertJsonValidationErrors('lines.1.purchase_order_line_id');

    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [orderLinePayload($order->lines[1])]])->assertCreated();
});

it('AC-004: the same document keeps its own order lines valid when edited, even if the order moved on', function () {
    $payload = purchaseInvoicePayload();
    $order = invoiceableOrderOf($payload);
    $id = $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [orderLinePayload($order->lines[0])]])->assertCreated()->json('data.id');
    $order->forceFill(['status' => PurchaseOrderStatus::Cancelled])->save();
    $document = PurchaseInvoice::query()->findOrFail($id);

    $this->putJson("/api/purchase-invoices/{$id}", purchaseInvoiceUpdatePayload($document, ['notes' => 'edited']))
        ->assertOk()->assertJsonPath('data.notes', 'edited')
        ->assertJsonPath('data.lines.0.purchase_order_line_id', $order->lines[0]->id);

    $draft = invoiceableOrderOf($payload, PurchaseOrderStatus::Draft);

    $this->putJson("/api/purchase-invoices/{$id}", purchaseInvoiceUpdatePayload($document->refresh(), ['lines' => [
        orderLinePayload($order->lines[0]),
        orderLinePayload($draft->lines[0]),
    ]]))->assertUnprocessable()->assertJsonValidationErrors('lines.1.purchase_order_line_id');
});

it('AC-004: the order line of a deleted document can be billed again; a billed order line cannot be deleted', function () {
    $payload = purchaseInvoicePayload();
    $order = invoiceableOrderOf($payload);
    $id = $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [orderLinePayload($order->lines[0])]])->assertCreated()->json('data.id');

    expect(fn () => $order->lines[0]->delete())->toThrow(QueryException::class);

    $this->deleteJson("/api/purchase-invoices/{$id}")->assertNoContent();
    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [orderLinePayload($order->lines[0])]])->assertCreated();
});
