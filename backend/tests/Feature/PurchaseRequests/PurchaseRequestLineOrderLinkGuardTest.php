<?php

use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequestLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function linkedLine(LineStatus $status, bool $linked = true): PurchaseRequestLine
{
    $line = purchaseRequestWithLines([$status], [], ['supplier_id' => supplierRegistry()->id])->lines->first();

    if ($linked) {
        PurchaseOrderLine::factory()->create(['purchase_request_line_id' => $line->id]);
    }

    return $line;
}

it('AC-010: an ordered line linked to an order line cannot leave ordered through the RDA status change', function (string $to) {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'manageStatuses']));
    $line = linkedLine(LineStatus::Ordered);

    $response = changeStatus($this, [$line->id], $to)->assertUnprocessable()->assertJsonValidationErrors('line_ids.0');

    expect($response->json('errors')['line_ids.0'])->toBe([__('purchase_request_lines.line_linked_to_order')]);

    expect($line->fresh()->status)->toBe(LineStatus::Ordered);
    $this->assertDatabaseCount('purchase_request_line_status_logs', 0);
})->with(['pending_approval', 'rejected']);

it('AC-010: a received line linked to an order line is guarded too', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'manageStatuses']));
    $line = linkedLine(LineStatus::Received);

    changeStatus($this, [$line->id], 'on_hold')->assertUnprocessable()->assertJsonValidationErrors('line_ids.0');

    expect($line->fresh()->status)->toBe(LineStatus::Received);
});

it('AC-010: an ordered line without a linked order line changes as before', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'manageStatuses']));
    $line = linkedLine(LineStatus::Ordered, linked: false);

    changeStatus($this, [$line->id], 'pending_approval')->assertOk();

    expect($line->fresh()->status)->toBe(LineStatus::PendingApproval);
});
