<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequestLine;
use App\Models\PurchaseRequestLineStatusLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * A draft order with one line linked to an ordered RDA line (the link is
 * written directly: only the generation from RDA does it through the API).
 *
 * @return array{0: PurchaseOrder, 1: PurchaseOrderLine, 2: PurchaseRequestLine}
 */
function orderLinkedToRequestLine(LineStatus $status = LineStatus::Ordered): array
{
    $requestLine = purchaseRequestWithLines([$status], [], ['oda_reference' => '9/2026'])->lines->first();
    $order = purchaseOrderWithLines(PurchaseOrderStatus::Draft, 2);
    $linked = $order->lines->first();
    $linked->forceFill(['purchase_request_line_id' => $requestLine->id])->save();

    return [$order->fresh('lines'), $linked, $requestLine];
}

it('AC-009: removing a linked line with PUT releases the request line, with a log and no ODA reference', function () {
    $actor = purchaseOrderUserWith(['view', 'update']);
    Sanctum::actingAs($actor);
    [$order, $linked, $requestLine] = orderLinkedToRequestLine();
    $payload = purchaseOrderUpdatePayload($order);
    $payload['lines'] = array_values(array_filter($payload['lines'], fn (array $line): bool => $line['id'] !== $linked->id));

    $this->putJson("/api/purchase-orders/{$order->id}", $payload)->assertOk()->assertJsonCount(1, 'data.lines');

    $fresh = $requestLine->fresh();
    $log = PurchaseRequestLineStatusLog::query()->where('purchase_request_line_id', $requestLine->id)->sole();

    expect($fresh->status)->toBe(LineStatus::Approved)
        ->and($fresh->oda_reference)->toBeNull()
        ->and($log->from_status)->toBe(LineStatus::Ordered)
        ->and($log->to_status)->toBe(LineStatus::Approved)
        ->and($log->user_id)->toBe($actor->id)
        ->and($log->reason)->not->toBeNull();
});

it('AC-009: deleting the order releases every linked request line', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'delete']));
    [$order, , $requestLine] = orderLinkedToRequestLine();

    $this->deleteJson("/api/purchase-orders/{$order->id}")->assertOk();

    expect(PurchaseOrder::query()->count())->toBe(0)
        ->and($requestLine->fresh()->status)->toBe(LineStatus::Approved)
        ->and($requestLine->fresh()->oda_reference)->toBeNull()
        ->and(PurchaseRequestLineStatusLog::query()->where('purchase_request_line_id', $requestLine->id)->count())->toBe(1);
});

it('AC-009: keeping the linked line leaves the request line ordered', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'update']));
    [$order, $linked, $requestLine] = orderLinkedToRequestLine();

    $this->putJson("/api/purchase-orders/{$order->id}", purchaseOrderUpdatePayload($order, ['description' => 'Edited']))
        ->assertOk()
        ->assertJsonPath('data.lines.0.purchase_request_line.id', $requestLine->id)
        ->assertJsonPath('data.lines.0.purchase_request_line.purchase_request_id', $requestLine->purchase_request_id);

    expect($requestLine->fresh()->status)->toBe(LineStatus::Ordered)
        ->and($requestLine->fresh()->oda_reference)->toBe('9/2026')
        ->and($linked->fresh()->purchase_request_line_id)->toBe($requestLine->id);
});

it('AC-009: the release needs no capability on the request, only the permission on the order', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'delete']));
    [$order] = orderLinkedToRequestLine();

    expect(auth()->user()->can('purchase-requests.fulfill'))->toBeFalse();
    $this->deleteJson("/api/purchase-orders/{$order->id}")->assertOk();
});

it('AC-009: a request line that is already approved only loses the ODA reference, without a log', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'delete']));
    [$order, , $requestLine] = orderLinkedToRequestLine(LineStatus::Approved);

    $this->deleteJson("/api/purchase-orders/{$order->id}")->assertOk();

    expect($requestLine->fresh()->oda_reference)->toBeNull()
        ->and(PurchaseRequestLineStatusLog::query()->count())->toBe(0);
});

it('AC-009: an order that cannot be deleted does not release anything', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'delete']));
    [$order, , $requestLine] = orderLinkedToRequestLine();
    $order->forceFill(['status' => PurchaseOrderStatus::Approved])->save();

    $this->deleteJson("/api/purchase-orders/{$order->id}")->assertStatus(409);

    expect($requestLine->fresh()->status)->toBe(LineStatus::Ordered)
        ->and($requestLine->fresh()->oda_reference)->toBe('9/2026');
});
