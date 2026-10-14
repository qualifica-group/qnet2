<?php

use App\Enums\PurchaseOrderStatus as Status;
use App\Models\PurchaseOrderReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function postOrderStatus(object $test, int $orderId, string $to): TestResponse
{
    return $test->postJson("/api/purchase-orders/{$orderId}/status", ['to_status' => $to]);
}

it('AC-006: a manual move to partially_fulfilled or fulfilled is a 422 on to_status', function (Status $from, string $to) {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'changeStatus', 'approve']));
    $order = purchaseOrderReceivable($from);

    postOrderStatus($this, $order->id, $to)->assertUnprocessable()->assertJsonValidationErrors(['to_status']);

    expect($order->fresh()->status)->toBe($from);
})->with([
    [Status::SentToSupplier, 'partially_fulfilled'],
    [Status::SentToSupplier, 'fulfilled'],
    [Status::PartiallyFulfilled, 'sent_to_supplier'],
    [Status::PartiallyFulfilled, 'fulfilled'],
    [Status::Fulfilled, 'partially_fulfilled'],
]);

it('AC-006: an order with receipts cannot go to in_validation, draft or cancelled', function (Status $from, string $to) {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'changeStatus', 'approve']));
    $order = purchaseOrderReceivable($from);
    purchaseOrderReceive($order, [$order->lines->first()->id => '1']);

    postOrderStatus($this, $order->id, $to)->assertUnprocessable()->assertJsonValidationErrors(['to_status']);

    expect($order->fresh()->status)->toBe($from);
})->with([
    [Status::Approved, 'in_validation'],
    [Status::Approved, 'cancelled'],
    [Status::SentToSupplier, 'cancelled'],
]);

it('AC-006: an order with a closed residual cannot be cancelled either', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'changeStatus']));
    $order = purchaseOrderReceivable(Status::Approved);
    $order->lines->first()->forceFill(['residual_closed_at' => now()])->save();

    postOrderStatus($this, $order->id, 'cancelled')->assertUnprocessable()->assertJsonValidationErrors(['to_status']);
});

it('AC-006: an order without receipts still goes to in_validation and cancelled', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'changeStatus']));
    $order = purchaseOrderReceivable(Status::Approved);

    postOrderStatus($this, $order->id, 'in_validation')->assertOk();
    postOrderStatus($this, $order->id, 'cancelled')->assertOk();
});

it('AC-006: abilities.transitions never list partially_fulfilled or fulfilled', function (Status $status, array $expected) {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'changeStatus', 'approve']));
    $order = purchaseOrderReceivable($status);

    $this->getJson("/api/purchase-orders/{$order->id}")->assertOk()->assertJsonPath('data.abilities.transitions', $expected);
})->with([
    [Status::SentToSupplier, ['cancelled']],
    [Status::PartiallyFulfilled, []],
    [Status::Fulfilled, []],
]);

it('D-3: the status follows the receipts and walks back to sent_to_supplier once nothing is received', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable(Status::Approved);
    [$first, $second] = $order->lines;
    $url = "/api/purchase-orders/{$order->id}/receipts";

    $a = $this->postJson($url, purchaseOrderReceiptPayload($order, [$first->id => '10']))->assertCreated()->json('data.receipt.id');
    expect($order->fresh()->status)->toBe(Status::PartiallyFulfilled);

    $b = $this->postJson($url, purchaseOrderReceiptPayload($order, [$second->id => '10']))->assertCreated()->json('data.receipt.id');
    expect($order->fresh()->status)->toBe(Status::Fulfilled);

    $this->deleteJson("{$url}/{$b}")->assertOk();
    expect($order->fresh()->status)->toBe(Status::PartiallyFulfilled);

    $this->deleteJson("{$url}/{$a}")->assertOk();
    expect($order->fresh()->status)->toBe(Status::SentToSupplier)
        ->and(PurchaseOrderReceipt::query()->count())->toBe(0);
});

it('D-5: a fulfilled order accepts a receipt cancellation but not a new receipt', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable(Status::SentToSupplier, 1);
    $line = $order->lines->first();
    $id = $this->postJson("/api/purchase-orders/{$order->id}/receipts", purchaseOrderReceiptPayload($order))->json('data.receipt.id');
    expect($order->fresh()->status)->toBe(Status::Fulfilled);

    $this->postJson("/api/purchase-orders/{$order->id}/receipts", purchaseOrderReceiptPayload($order, [$line->id => '1']))->assertStatus(409);
    $this->deleteJson("/api/purchase-orders/{$order->id}/receipts/{$id}")->assertOk()->assertJsonPath('data.status', 'sent_to_supplier');
});
