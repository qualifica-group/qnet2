<?php

use App\Enums\PurchaseOrderStatus as Status;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function postStatus(object $test, PurchaseOrder $order, string $to): TestResponse
{
    return $test->postJson("/api/purchase-orders/{$order->id}/status", ['to_status' => $to]);
}

it('AC-006: draft -> sent_to_supplier is a 422 on to_status and the order is unchanged', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'changeStatus', 'approve']));
    $order = purchaseOrderWithLines();

    postStatus($this, $order, 'sent_to_supplier')->assertUnprocessable()->assertJsonValidationErrors(['to_status']);

    expect($order->fresh()->status)->toBe(Status::Draft);
});

it('AC-006: approved -> sent_to_supplier with changeStatus answers the order', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'changeStatus']));
    $order = purchaseOrderWithLines(Status::Approved);

    postStatus($this, $order, 'sent_to_supplier')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'sent_to_supplier')
        ->assertJsonPath('data.id', $order->id);

    expect($order->fresh()->status)->toBe(Status::SentToSupplier);
});

it('AC-006: moving to approved needs the approve permission, changeStatus is not enough', function () {
    $order = purchaseOrderWithLines();

    Sanctum::actingAs(purchaseOrderUserWith(['view', 'changeStatus']));
    postStatus($this, $order, 'approved')->assertForbidden();
    expect($order->fresh()->status)->toBe(Status::Draft);

    Sanctum::actingAs(purchaseOrderUserWith(['view', 'approve']));
    postStatus($this, $order, 'approved')->assertOk()->assertJsonPath('data.status', 'approved');
});

it('AC-006: every other transition needs changeStatus, approve is not enough', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'approve']));
    $order = purchaseOrderWithLines();

    postStatus($this, $order, 'in_validation')->assertForbidden();
    postStatus($this, $order, 'cancelled')->assertForbidden();
});

it('AC-006: the status endpoint needs view, and an unknown status is a 422', function () {
    $order = purchaseOrderWithLines();

    Sanctum::actingAs(purchaseOrderUserWith(['changeStatus']));
    postStatus($this, $order, 'in_validation')->assertForbidden();

    Sanctum::actingAs(purchaseOrderUserWith(['view', 'changeStatus']));
    postStatus($this, $order, 'bogus')->assertUnprocessable()->assertJsonValidationErrors(['to_status']);
    $this->postJson('/api/purchase-orders/999999/status', ['to_status' => 'cancelled'])->assertNotFound();
});

it('AC-006: a cancelled order can go back to draft and the whole matrix is honoured', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'changeStatus', 'approve']));

    foreach (Status::cases() as $from) {
        foreach (Status::cases() as $to) {
            $order = purchaseOrderWithLines($from);
            $response = postStatus($this, $order, $to->value);

            $from->canTransitionTo($to)
                ? $response->assertOk()->assertJsonPath('data.status', $to->value)
                : $response->assertUnprocessable();
        }
    }
});

it('AC-006: abilities.transitions are the matrix filtered by permission', function (array $abilities, array $expected) {
    Sanctum::actingAs(purchaseOrderUserWith(['view', ...$abilities]));
    $order = purchaseOrderWithLines(Status::Draft);

    $this->getJson("/api/purchase-orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.abilities.transitions', $expected);
})->with([
    'both permissions' => [['changeStatus', 'approve'], ['in_validation', 'approved', 'cancelled']],
    'changeStatus only' => [['changeStatus'], ['in_validation', 'cancelled']],
    'approve only' => [['approve'], ['approved']],
    'none' => [[], []],
]);

it('AC-006: the transitions of an approved order never include states outside the matrix', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'changeStatus', 'approve']));
    $order = purchaseOrderWithLines(Status::Approved);

    $this->getJson("/api/purchase-orders/{$order->id}")
        ->assertJsonPath('data.abilities.transitions', ['in_validation', 'sent_to_supplier', 'cancelled']);
});
