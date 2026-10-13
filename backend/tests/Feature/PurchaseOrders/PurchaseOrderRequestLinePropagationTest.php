<?php

use App\Enums\PurchaseOrderStatus as OrderStatus;
use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\PurchaseRequestLineStatusLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * An order whose first line is linked to the first request line (the link is
 * written directly: only the generation from RDA does it through the API).
 *
 * @param  array<int, LineStatus>  $requestStatuses  the first one is the linked line
 * @return array{0: PurchaseOrder, 1: PurchaseOrderLine, 2: PurchaseRequest}
 */
function orderLinkedTo(OrderStatus $orderStatus, array $requestStatuses, bool $closedRequest = false): array
{
    $request = purchaseRequestWithLines($requestStatuses, $closedRequest ? ['status' => PurchaseRequestStatus::Closed] : [], ['oda_reference' => '9/2026']);
    $order = purchaseOrderWithLines($orderStatus, 2);
    $linked = $order->lines->first();
    $linked->forceFill(['purchase_request_line_id' => $request->lines->first()->id])->save();

    return [$order->fresh('lines'), $linked, $request];
}

function linkedRequestLine(PurchaseRequest $request): PurchaseRequestLine
{
    return $request->lines->first()->fresh();
}

function linkedLineLogReason(PurchaseRequestLine $line): ?string
{
    return PurchaseRequestLineStatusLog::query()->where('purchase_request_line_id', $line->id)->sole()->reason;
}

it('AC-003: deleting a draft order releases an ordered line of an open request to approved, with a log', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'delete']));
    [$order, , $request] = orderLinkedTo(OrderStatus::Draft, [LineStatus::Ordered]);

    $this->deleteJson("/api/purchase-orders/{$order->id}")->assertOk();

    $line = linkedRequestLine($request);
    expect($line->status)->toBe(LineStatus::Approved)
        ->and($line->oda_reference)->toBeNull()
        ->and(linkedLineLogReason($line))->toBe(__('purchase_request_line_links.order_deleted'));
});

it('AC-004: with a closed request the ordered line goes on hold and the request stays closed', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'delete']));
    [$order, , $request] = orderLinkedTo(OrderStatus::Draft, [LineStatus::Ordered], closedRequest: true);

    $this->deleteJson("/api/purchase-orders/{$order->id}")->assertOk();

    $line = linkedRequestLine($request);
    expect($line->status)->toBe(LineStatus::OnHold)
        ->and($line->oda_reference)->toBeNull()
        ->and($request->fresh()->isClosed())->toBeTrue()
        ->and(PurchaseRequestLineStatusLog::query()->where('purchase_request_line_id', $line->id)->count())->toBe(1);
});

it('AC-005: received and rejected lines keep their state and only lose the ODA reference', function (LineStatus $status) {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'delete']));
    [$order, , $request] = orderLinkedTo(OrderStatus::Draft, [$status]);

    $this->deleteJson("/api/purchase-orders/{$order->id}")->assertOk();

    $line = linkedRequestLine($request);
    expect($line->status)->toBe($status)
        ->and($line->oda_reference)->toBeNull()
        ->and(PurchaseRequestLineStatusLog::query()->count())->toBe(0);
})->with([LineStatus::Received, LineStatus::Rejected]);

it('AC-006: removing a linked line with PUT follows the same rule (open and closed request)', function (bool $closed, LineStatus $expected) {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'update']));
    [$order, $linked, $request] = orderLinkedTo(OrderStatus::Draft, [LineStatus::Ordered], $closed);
    $remaining = array_values(array_filter(
        purchaseOrderUpdatePayload($order)['lines'],
        fn (array $line): bool => $line['id'] !== $linked->id,
    ));

    $this->putJson("/api/purchase-orders/{$order->id}", purchaseOrderUpdatePayload($order, ['lines' => $remaining]))->assertOk();

    $line = linkedRequestLine($request);
    expect($line->status)->toBe($expected)->and($line->oda_reference)->toBeNull();
})->with([[false, LineStatus::Approved], [true, LineStatus::OnHold]]);

it('AC-007: cancelling releases the request lines, clears the link, and going back to draft changes nothing', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'changeStatus']));
    [$order, $linked, $request] = orderLinkedTo(OrderStatus::Approved, [LineStatus::Ordered]);

    $this->postJson("/api/purchase-orders/{$order->id}/status", ['to_status' => 'cancelled'])->assertOk();

    $line = linkedRequestLine($request);
    expect($line->status)->toBe(LineStatus::Approved)
        ->and($line->oda_reference)->toBeNull()
        ->and($linked->fresh()->purchase_request_line_id)->toBeNull()
        ->and(linkedLineLogReason($line))->toBe(__('purchase_request_line_links.order_cancelled'));

    $this->postJson("/api/purchase-orders/{$order->id}/status", ['to_status' => 'draft'])->assertOk();

    expect(linkedRequestLine($request)->status)->toBe(LineStatus::Approved)
        ->and(linkedRequestLine($request)->oda_reference)->toBeNull()
        ->and($linked->fresh()->purchase_request_line_id)->toBeNull();
});

it('AC-008: fulfilling receives the ordered lines and closes a request that is complete', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'changeStatus']));
    [$order, , $request] = orderLinkedTo(OrderStatus::SentToSupplier, [LineStatus::Ordered, LineStatus::Rejected]);

    $this->postJson("/api/purchase-orders/{$order->id}/status", ['to_status' => 'fulfilled'])->assertOk();

    $line = linkedRequestLine($request);
    expect($line->status)->toBe(LineStatus::Received)
        ->and(linkedLineLogReason($line))->toBe(__('purchase_request_line_links.order_fulfilled'))
        ->and($request->fresh()->isClosed())->toBeTrue();
});

it('AC-008: a request with another open line stays open after fulfilling', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'changeStatus']));
    [$order, , $request] = orderLinkedTo(OrderStatus::SentToSupplier, [LineStatus::Ordered, LineStatus::Approved]);

    $this->postJson("/api/purchase-orders/{$order->id}/status", ['to_status' => 'fulfilled'])->assertOk();

    expect(linkedRequestLine($request)->status)->toBe(LineStatus::Received)
        ->and($request->fresh()->isClosed())->toBeFalse();
});

it('AC-009: going from fulfilled back to partially_fulfilled leaves the request lines received', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'changeStatus']));
    [$order, , $request] = orderLinkedTo(OrderStatus::SentToSupplier, [LineStatus::Ordered, LineStatus::Approved]);

    $this->postJson("/api/purchase-orders/{$order->id}/status", ['to_status' => 'fulfilled'])->assertOk();
    $this->postJson("/api/purchase-orders/{$order->id}/status", ['to_status' => 'partially_fulfilled'])->assertOk();

    expect(linkedRequestLine($request)->status)->toBe(LineStatus::Received);
});
