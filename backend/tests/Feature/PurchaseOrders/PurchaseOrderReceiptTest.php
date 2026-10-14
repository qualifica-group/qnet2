<?php

use App\Enums\PurchaseOrderStatus as Status;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function receiptsUrl(PurchaseOrder $order, string $suffix = ''): string
{
    return "/api/purchase-orders/{$order->id}/receipts{$suffix}";
}

function receivedOf(PurchaseOrder $order, int $index): string
{
    return $order->fresh('lines')->lines[$index]->received_quantity;
}

it('permission purchase-orders.receive exists after permissions:sync', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::query()->where('name', 'purchase-orders.receive')->exists())->toBeTrue();
});

it('AC-002: a partial receipt answers 201 with the receipt and the order, and the order becomes partially_fulfilled', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable();
    [$first] = $order->lines;

    $this->postJson(receiptsUrl($order), purchaseOrderReceiptPayload($order, [$first->id => '4.5'], [
        'delivery_note_number' => 'DDT-1', 'delivery_note_date' => '2026-10-01', 'notes' => 'Pallet 1',
    ]))
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.receipt.delivery_note_number', 'DDT-1')
        ->assertJsonPath('data.receipt.lines.0.purchase_order_line_id', $first->id)
        ->assertJsonPath('data.receipt.lines.0.quantity', '4.500')
        ->assertJsonPath('data.receipt.lines.0.position', 1)
        ->assertJsonPath('data.receipt.abilities.delete', true)
        ->assertJsonPath('data.order.status', 'partially_fulfilled')
        ->assertJsonPath('data.order.lines.0.received_quantity', '4.500')
        ->assertJsonPath('data.order.lines.0.residual_quantity', '5.500')
        ->assertJsonPath('data.order.lines.0.fulfillment', 'partial')
        ->assertJsonPath('data.order.lines.1.fulfillment', 'open');

    expect(receivedOf($order, 0))->toBe('4.500')
        ->and($order->fresh()->status)->toBe(Status::PartiallyFulfilled);
});

it('AC-002: the receipt that completes every line makes the order fulfilled', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable();
    [$first, $second] = $order->lines;

    $this->postJson(receiptsUrl($order), purchaseOrderReceiptPayload($order, [$first->id => '10', $second->id => '3']))->assertCreated();
    $this->postJson(receiptsUrl($order), purchaseOrderReceiptPayload($order, [$second->id => '7']))
        ->assertCreated()
        ->assertJsonPath('data.order.status', 'fulfilled')
        ->assertJsonPath('data.order.abilities.receive', true);

    expect(receivedOf($order, 1))->toBe('10.000');
});

it('AC-003: a quantity over the residual is a 422 on lines.N.quantity and nothing is written', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable();
    [$first, $second] = $order->lines;
    purchaseOrderReceive($order, [$second->id => '8']);

    $this->postJson(receiptsUrl($order), purchaseOrderReceiptPayload($order, [$first->id => '2', $second->id => '3']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lines.1.quantity'])
        ->assertJsonMissingValidationErrors(['lines.0.quantity']);

    expect(PurchaseOrderReceipt::query()->count())->toBe(1)
        ->and(receivedOf($order, 0))->toBe('0.000');
});

it('AC-003: a line of another order, a closed residual and a duplicate line are 422 on the line', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable();
    $foreign = purchaseOrderReceivable()->lines->first();
    [$first, $second] = $order->lines;
    $second->forceFill(['residual_closed_at' => now()])->save();

    $this->postJson(receiptsUrl($order), purchaseOrderReceiptPayload($order, [$foreign->id => '1']))
        ->assertUnprocessable()->assertJsonValidationErrors(['lines.0.purchase_order_line_id']);

    $this->postJson(receiptsUrl($order), purchaseOrderReceiptPayload($order, [$second->id => '1']))
        ->assertUnprocessable()->assertJsonValidationErrors(['lines.0.purchase_order_line_id']);

    $this->postJson(receiptsUrl($order), ['receipt_date' => now()->toDateString(), 'lines' => [
        ['purchase_order_line_id' => $first->id, 'quantity' => '1'],
        ['purchase_order_line_id' => $first->id, 'quantity' => '1'],
    ]])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.purchase_order_line_id', 'lines.1.purchase_order_line_id']);

    expect(PurchaseOrderReceipt::query()->count())->toBe(0);
});

it('AC-003: the payload is validated (future date, quantity, lengths, line count)', function (array $overrides, string $field) {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable();

    $this->postJson(receiptsUrl($order), purchaseOrderReceiptPayload($order, null, $overrides))
        ->assertUnprocessable()->assertJsonValidationErrors([$field]);
})->with([
    'future date' => [['receipt_date' => '2999-01-01'], 'receipt_date'],
    'missing date' => [['receipt_date' => null], 'receipt_date'],
    'long note number' => [['delivery_note_number' => str_repeat('x', 51)], 'delivery_note_number'],
    'long notes' => [['notes' => str_repeat('x', 2001)], 'notes'],
    'no lines' => [['lines' => []], 'lines'],
    'zero quantity' => [['lines' => [['purchase_order_line_id' => 1, 'quantity' => '0']]], 'lines.0.quantity'],
    'four decimals' => [['lines' => [['purchase_order_line_id' => 1, 'quantity' => '1.2345']]], 'lines.0.quantity'],
]);

it('AC-003: an order that is not receivable is a 409', function (Status $status) {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable($status);

    $this->postJson(receiptsUrl($order), purchaseOrderReceiptPayload($order))->assertStatus(409)->assertJsonPath('success', false);

    expect(PurchaseOrderReceipt::query()->count())->toBe(0);
})->with([Status::Draft, Status::InValidation, Status::Cancelled, Status::Fulfilled]);

it('AC-003: without receive it is a 403, and an unknown order a 404', function () {
    $order = purchaseOrderReceivable();

    Sanctum::actingAs(purchaseOrderUserWith(['view', 'update', 'changeStatus']));
    $this->postJson(receiptsUrl($order), purchaseOrderReceiptPayload($order))->assertForbidden();
    $this->postJson('/api/purchase-orders/999999/receipts', purchaseOrderReceiptPayload($order))->assertNotFound();

    $receipt = purchaseOrderReceive($order, [$order->lines->first()->id => '1']);
    $this->deleteJson(receiptsUrl($order, "/{$receipt->id}"))->assertForbidden();

    expect(PurchaseOrderReceipt::query()->count())->toBe(1);
});

it('AC-004: deleting a receipt recomputes the quantities and walks the status back', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable();
    [$first, $second] = $order->lines;

    $one = $this->postJson(receiptsUrl($order), purchaseOrderReceiptPayload($order, [$first->id => '10', $second->id => '4']))->json('data.receipt.id');
    $two = $this->postJson(receiptsUrl($order), purchaseOrderReceiptPayload($order, [$second->id => '6']))->json('data.receipt.id');
    expect($order->fresh()->status)->toBe(Status::Fulfilled);

    $this->deleteJson(receiptsUrl($order, "/{$two}"))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'partially_fulfilled')
        ->assertJsonPath('data.lines.1.received_quantity', '4.000');

    $this->deleteJson(receiptsUrl($order, "/{$one}"))->assertOk()->assertJsonPath('data.status', 'sent_to_supplier');

    expect(receivedOf($order, 0))->toBe('0.000')
        ->and(PurchaseOrderReceipt::query()->count())->toBe(0)
        ->and(PurchaseOrderReceiptLine::query()->count())->toBe(0);
});

it('AC-004: deleting a receipt is logged in the activity log', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable();
    $id = $this->postJson(receiptsUrl($order), purchaseOrderReceiptPayload($order))->json('data.receipt.id');

    $this->deleteJson(receiptsUrl($order, "/{$id}"))->assertOk();

    expect(Activity::query()->where('subject_type', (new PurchaseOrderReceipt)->getMorphClass())->where('subject_id', $id)->where('event', 'deleted')->exists())->toBeTrue();
});

it('AC-004: a receipt of another order is a 404, a cancelled order a 409', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable();
    $other = purchaseOrderReceivable();
    $foreign = purchaseOrderReceive($other, [$other->lines->first()->id => '1']);

    $this->deleteJson(receiptsUrl($order, "/{$foreign->id}"))->assertNotFound();
    expect(PurchaseOrderReceipt::query()->count())->toBe(1);

    $mine = purchaseOrderReceive($order, [$order->lines->first()->id => '1']);
    $order->forceFill(['status' => Status::Cancelled])->save();

    $this->deleteJson(receiptsUrl($order, "/{$mine->id}"))->assertStatus(409);
    expect(PurchaseOrderReceipt::query()->count())->toBe(2);
});

it('AC-004: a receipt that would drop the received quantity under the invoiced one is a 409', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable();
    $line = $order->lines->first();
    $receipt = purchaseOrderReceive($order, [$line->id => '5']);
    PurchaseInvoiceLine::factory()->create(['purchase_order_line_id' => $line->id, 'quantity' => '3.000']);

    $this->deleteJson(receiptsUrl($order, "/{$receipt->id}"))->assertStatus(409);

    expect(receivedOf($order, 0))->toBe('5.000')
        ->and(PurchaseOrderReceipt::query()->count())->toBe(1);
});

it('AC-004: a proforma does not block the cancellation of a receipt while the residual is open', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable();
    $line = $order->lines->first();
    $receipt = purchaseOrderReceive($order, [$line->id => '5']);
    PurchaseInvoiceLine::factory()->create([
        'purchase_invoice_id' => PurchaseInvoice::factory()->proforma()->create()->id,
        'purchase_order_line_id' => $line->id,
        'quantity' => '4.000',
    ]);

    $this->deleteJson(receiptsUrl($order, "/{$receipt->id}"))->assertOk();
});

it('AC-011: GET receipts lists the receipts by date desc then id desc, with abilities', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable();
    $line = $order->lines->first();
    $old = purchaseOrderReceive($order, [$line->id => '1'], ['receipt_date' => '2026-09-01']);
    $newA = purchaseOrderReceive($order, [$line->id => '1'], ['receipt_date' => '2026-10-01']);
    $newB = purchaseOrderReceive($order, [$line->id => '1'], ['receipt_date' => '2026-10-01']);

    $this->getJson(receiptsUrl($order))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.id', $newB->id)
        ->assertJsonPath('data.1.id', $newA->id)
        ->assertJsonPath('data.2.id', $old->id)
        ->assertJsonPath('data.0.receipt_date', '2026-10-01')
        ->assertJsonPath('data.0.created_by.id', $newB->created_by)
        ->assertJsonPath('data.0.abilities.delete', true);
});

it('AC-011: GET receipts needs view, and the delete ability follows receive', function () {
    $order = purchaseOrderReceivable();
    purchaseOrderReceive($order, [$order->lines->first()->id => '1']);

    Sanctum::actingAs(purchaseOrderUserWith(['receive']));
    $this->getJson(receiptsUrl($order))->assertForbidden();

    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    $this->getJson(receiptsUrl($order))->assertOk()->assertJsonPath('data.0.abilities.delete', false);
});

it('AC-011: the order resource exposes the line fields and abilities.receive', function (array $abilities, Status $status, bool $expected) {
    Sanctum::actingAs(purchaseOrderUserWith(['view', ...$abilities]));
    $order = purchaseOrderReceivable($status);
    $line = $order->lines->first();
    $closer = User::factory()->create();
    $line->forceFill(['received_quantity' => '2.000', 'residual_closed_at' => now(), 'residual_closed_by_id' => $closer->id, 'residual_close_reason' => 'Supplier closed'])->save();
    PurchaseInvoiceLine::factory()->create(['purchase_order_line_id' => $line->id, 'quantity' => '1.500']);

    $this->getJson("/api/purchase-orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.abilities.receive', $expected)
        ->assertJsonPath('data.lines.0.received_quantity', '2.000')
        ->assertJsonPath('data.lines.0.invoiced_quantity', '1.500')
        ->assertJsonPath('data.lines.0.invoiced_invoice_quantity', '1.500')
        ->assertJsonPath('data.lines.0.residual_quantity', '0.000')
        ->assertJsonPath('data.lines.0.fulfillment', 'complete')
        ->assertJsonPath('data.lines.0.residual_closed.reason', 'Supplier closed')
        ->assertJsonPath('data.lines.0.residual_closed.by.id', $closer->id)
        ->assertJsonPath('data.lines.1.residual_closed', null)
        ->assertJsonPath('data.lines.1.received_quantity', '0.000');
})->with([
    'receivable' => [['receive'], Status::SentToSupplier, true],
    'fulfilled' => [['receive'], Status::Fulfilled, true],
    'draft' => [['receive'], Status::Draft, false],
    'cancelled' => [['receive'], Status::Cancelled, false],
    'no permission' => [[], Status::SentToSupplier, false],
]);
