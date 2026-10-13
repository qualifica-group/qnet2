<?php

declare(strict_types=1);

use App\Enums\PurchaseOrderStatus;
use App\Jobs\SendOutboundEmailJob;
use App\Models\OutboundEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

// spec 0244 -- ODA-08: the order is emailed to the supplier only when approved or already sent.

uses(RefreshDatabase::class);

dataset('not sendable statuses', [
    PurchaseOrderStatus::Draft,
    PurchaseOrderStatus::InValidation,
    PurchaseOrderStatus::PartiallyFulfilled,
    PurchaseOrderStatus::Fulfilled,
    PurchaseOrderStatus::Cancelled,
]);

dataset('sendable statuses', [PurchaseOrderStatus::Approved, PurchaseOrderStatus::SentToSupplier]);

it('AC-013: creating an email draft is a 409 and leaves nothing behind', function (PurchaseOrderStatus $status) {
    $order = purchaseOrderWithLines($status);
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'send']));

    $this->postJson("/api/purchase-orders/{$order->id}/emails", ['attach_pdf' => false])
        ->assertStatus(409)
        ->assertJsonPath('message', __('purchase_orders.not_sendable_status'));

    expect(OutboundEmail::query()->count())->toBe(0);
})->with('not sendable statuses');

it('AC-013: sending an existing draft is a 409 once the order is not sendable', function (PurchaseOrderStatus $status) {
    Queue::fake();
    $order = purchaseOrderWithLines(PurchaseOrderStatus::Approved);
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'send']));
    $id = $this->postJson("/api/purchase-orders/{$order->id}/emails", ['to' => ['a@supplier.test'], 'subject' => 's', 'body' => '<p>b</p>', 'attach_pdf' => false])
        ->assertCreated()->json('data.id');
    $order->forceFill(['status' => $status])->save();

    $this->postJson("/api/purchase-orders/{$order->id}/emails/{$id}/send")
        ->assertStatus(409)
        ->assertJsonPath('message', __('purchase_orders.not_sendable_status'));

    Queue::assertNotPushed(SendOutboundEmailJob::class);
})->with('not sendable statuses');

it('AC-013: an approved or sent order can create and send the email (regression)', function (PurchaseOrderStatus $status) {
    Queue::fake();
    $order = purchaseOrderWithLines($status);
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'send']));
    $id = $this->postJson("/api/purchase-orders/{$order->id}/emails", ['to' => ['a@supplier.test'], 'subject' => 's', 'body' => '<p>b</p>', 'attach_pdf' => false])
        ->assertCreated()->json('data.id');

    $this->postJson("/api/purchase-orders/{$order->id}/emails/{$id}/send")->assertStatus(202);

    Queue::assertPushed(SendOutboundEmailJob::class);
})->with('sendable statuses');

it('AC-014: abilities.send needs the permission and a sendable status', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'send']));

    foreach (PurchaseOrderStatus::cases() as $status) {
        $order = purchaseOrderWithLines($status);

        $this->getJson("/api/purchase-orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.abilities.send', $status->isSendable());
    }

    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    $approved = purchaseOrderWithLines(PurchaseOrderStatus::Approved);

    $this->getJson("/api/purchase-orders/{$approved->id}")->assertOk()->assertJsonPath('data.abilities.send', false);
});
