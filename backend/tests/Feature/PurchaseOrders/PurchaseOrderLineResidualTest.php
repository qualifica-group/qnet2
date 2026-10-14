<?php

use App\Enums\PurchaseOrderStatus as Status;
use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function residualUrl(PurchaseOrder $order, PurchaseOrderLine $line): string
{
    return "/api/purchase-orders/{$order->id}/lines/{$line->id}/close-residual";
}

it('AC-005: closing the residual of a line with a reason completes it and answers the order', function () {
    $actor = purchaseOrderUserWith(['view', 'receive']);
    Sanctum::actingAs($actor);
    $order = purchaseOrderReceivable();
    [$first, $second] = $order->lines;
    purchaseOrderReceive($order, [$first->id => '10', $second->id => '6']);

    $this->postJson(residualUrl($order, $second), ['reason' => 'Supplier will not ship the rest'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'fulfilled')
        ->assertJsonPath('data.lines.1.fulfillment', 'complete')
        ->assertJsonPath('data.lines.1.residual_quantity', '0.000')
        ->assertJsonPath('data.lines.1.residual_closed.reason', 'Supplier will not ship the rest')
        ->assertJsonPath('data.lines.1.residual_closed.by.id', $actor->id);

    $line = $second->fresh();
    expect($line->residual_closed_at)->not->toBeNull()
        ->and($line->residual_closed_by_id)->toBe($actor->id);
});

it('AC-005: closing one line of an untouched order makes it partially_fulfilled', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable(Status::Approved);

    $this->postJson(residualUrl($order, $order->lines->first()), ['reason' => 'No longer needed'])
        ->assertOk()->assertJsonPath('data.status', 'partially_fulfilled');
});

it('AC-005: the reason is required and at most 500 characters', function (mixed $reason) {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable();

    $this->postJson(residualUrl($order, $order->lines->first()), ['reason' => $reason])
        ->assertUnprocessable()->assertJsonValidationErrors(['reason']);

    expect($order->lines->first()->fresh()->residual_closed_at)->toBeNull();
})->with([[null], [''], [str_repeat('x', 501)]]);

it('AC-005: a complete or already closed line, and a billed quantity above the received one, are 409', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable();
    [$first, $second] = $order->lines;
    purchaseOrderReceive($order, [$first->id => '10', $second->id => '4']);

    $this->postJson(residualUrl($order, $first), ['reason' => 'x'])->assertStatus(409);

    PurchaseInvoiceLine::factory()->create(['purchase_order_line_id' => $second->id, 'quantity' => '5.000']);
    $this->postJson(residualUrl($order, $second), ['reason' => 'x'])->assertStatus(409);
    expect($second->fresh()->residual_closed_at)->toBeNull();

    PurchaseInvoiceLine::query()->delete();
    $this->postJson(residualUrl($order, $second), ['reason' => 'x'])->assertOk();
    $this->postJson(residualUrl($order, $second), ['reason' => 'again'])->assertStatus(409);
});

it('AC-005: closing needs a receivable order, receive, and a line of that order', function () {
    $draft = purchaseOrderReceivable(Status::Draft);
    $order = purchaseOrderReceivable();
    $foreign = purchaseOrderReceivable()->lines->first();

    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $this->postJson(residualUrl($draft, $draft->lines->first()), ['reason' => 'x'])->assertStatus(409);
    $this->postJson(residualUrl($order, $foreign), ['reason' => 'x'])->assertNotFound();

    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    $this->postJson(residualUrl($order, $order->lines->first()), ['reason' => 'x'])->assertForbidden();
    $this->deleteJson(residualUrl($order, $order->lines->first()))->assertForbidden();
});

it('AC-005: reopening a closed residual opens the line again and recomputes the status', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable();
    [$first, $second] = $order->lines;
    purchaseOrderReceive($order, [$first->id => '10', $second->id => '6']);
    $this->postJson(residualUrl($order, $second), ['reason' => 'x'])->assertOk()->assertJsonPath('data.status', 'fulfilled');

    $this->deleteJson(residualUrl($order, $second))
        ->assertOk()
        ->assertJsonPath('data.status', 'partially_fulfilled')
        ->assertJsonPath('data.lines.1.fulfillment', 'partial')
        ->assertJsonPath('data.lines.1.residual_quantity', '4.000')
        ->assertJsonPath('data.lines.1.residual_closed', null);

    $line = $second->fresh();
    expect($line->residual_closed_at)->toBeNull()
        ->and($line->residual_closed_by_id)->toBeNull()
        ->and($line->residual_close_reason)->toBeNull();
});

it('AC-005: reopening a residual that is not closed, or on a cancelled order, is a 409', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable();
    $line = $order->lines->first();

    $this->deleteJson(residualUrl($order, $line))->assertStatus(409);

    $line->forceFill(['residual_closed_at' => now()])->save();
    $order->forceFill(['status' => Status::Cancelled])->save();
    $this->deleteJson(residualUrl($order, $line))->assertStatus(409);
});

it('AC-005: a fulfilled order accepts the reopening and closing the last residual propagates to the request line', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $request = purchaseRequestWithLines([LineStatus::Ordered, LineStatus::Approved], [], ['oda_reference' => '9/2026']);
    $order = purchaseOrderReceivable(Status::SentToSupplier, 1);
    $line = $order->lines->first();
    $line->forceFill(['purchase_request_line_id' => $request->lines->first()->id])->save();

    $this->postJson(residualUrl($order, $line), ['reason' => 'x'])->assertOk()->assertJsonPath('data.status', 'fulfilled');
    expect($request->lines->first()->fresh()->status)->toBe(LineStatus::Received);

    $this->deleteJson(residualUrl($order, $line))->assertOk()->assertJsonPath('data.status', 'sent_to_supplier');
    expect($request->lines->first()->fresh()->status)->toBe(LineStatus::Ordered);
});
