<?php

use App\Enums\PurchaseOrderMergeAction;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderMergeLog;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * A destination order (1 line) and a source (2 lines) of the same supplier and company.
 *
 * @return array{0: PurchaseOrder, 1: PurchaseOrder}
 */
function mergePair(PurchaseOrderStatus $sourceStatus = PurchaseOrderStatus::Draft, PurchaseOrderStatus $destinationStatus = PurchaseOrderStatus::Draft): array
{
    $supplier = Registry::factory()->supplier()->create();
    $company = Company::factory()->create();
    $shared = ['supplier_id' => $supplier->id, 'company_id' => $company->id];

    return [
        purchaseOrderWithLines($destinationStatus, 1, $shared),
        purchaseOrderWithLines($sourceStatus, 2, $shared),
    ];
}

/**
 * @param  array<int, int>  $lineIds
 */
function mergeInto(object $test, PurchaseOrder $destination, array $lineIds): TestResponse
{
    return $test->postJson("/api/purchase-orders/{$destination->id}/merge", ['line_ids' => $lineIds]);
}

it('AC-010: moving every line of a source deletes it and logs a snapshot, lines keep their id', function () {
    $actor = purchaseOrderUserWith(['merge']);
    Sanctum::actingAs($actor);
    [$destination, $source] = mergePair();
    $moved = $source->lines->pluck('id')->all();
    $sourceCode = $source->code();

    mergeInto($this, $destination, $moved)
        ->assertOk()
        ->assertJsonPath('data.id', $destination->id)
        ->assertJsonPath('data.grand_total', '300.00')
        ->assertJsonPath('data.merge_logs_count', 1)
        ->assertJsonPath('meta.deleted_purchase_order_ids', [$source->id])
        ->assertJsonCount(3, 'data.lines');

    $lines = $destination->fresh('lines')->lines;
    $log = PurchaseOrderMergeLog::query()->sole();

    expect(PurchaseOrder::query()->find($source->id))->toBeNull()
        ->and($lines->pluck('id')->all())->toBe([$destination->lines->first()->id, ...$moved])
        ->and($lines->pluck('position')->all())->toBe([1, 2, 3])
        ->and($destination->fresh()->taxable_total)->toBe('300.00')
        ->and($log->source_action)->toBe(PurchaseOrderMergeAction::Deleted)
        ->and($log->source_purchase_order_id)->toBeNull()
        ->and($log->source_code)->toBe($sourceCode)
        ->and($log->destination_purchase_order_id)->toBe($destination->id)
        ->and($log->moved_line_ids)->toBe($moved)
        ->and($log->performed_by)->toBe($actor->id)
        ->and($log->source_snapshot['header']['id'])->toBe($source->id)
        ->and($log->source_snapshot['header']['grand_total'])->toBe('200.00')
        ->and(array_column($log->source_snapshot['lines'], 'id'))->toBe($moved);
});

it('AC-010: moving part of a source keeps it, with recomputed totals, and logs a partial merge', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['merge']));
    [$destination, $source] = mergePair();
    $first = $source->lines->first();

    mergeInto($this, $destination, [$first->id])
        ->assertOk()
        ->assertJsonPath('data.grand_total', '200.00')
        ->assertJsonPath('meta.deleted_purchase_order_ids', []);

    $log = PurchaseOrderMergeLog::query()->sole();

    expect($source->fresh()->grand_total)->toBe('100.00')
        ->and($source->fresh()->lines)->toHaveCount(1)
        ->and($first->fresh()->purchase_order_id)->toBe($destination->id)
        ->and($log->source_action)->toBe(PurchaseOrderMergeAction::Partial)
        ->and($log->source_purchase_order_id)->toBe($source->id)
        ->and($log->source_snapshot['header']['grand_total'])->toBe('200.00');
});

it('AC-010: a moved line linked to a request line follows the order and the reference points to the destination', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['merge']));
    [$destination, $source] = mergePair();
    $requestLine = purchaseRequestWithLines([LineStatus::Ordered], [], ['oda_reference' => $source->code()])->lines->first();
    $source->lines->first()->forceFill(['purchase_request_line_id' => $requestLine->id])->save();

    // every line moves, so the source is deleted: the request line must stay linked
    mergeInto($this, $destination, $source->lines->pluck('id')->all())->assertOk();

    expect($requestLine->fresh()->oda_reference)->toBe($destination->code())
        ->and($requestLine->fresh()->status)->toBe(LineStatus::Ordered)
        ->and(PurchaseOrderLine::query()->where('purchase_request_line_id', $requestLine->id)->sole()->purchase_order_id)->toBe($destination->id)
        ->and($requestLine->statusLogs()->count())->toBe(0);
});

it('AC-011: a line of another supplier or company is refused with 422 and nothing moves', function (string $difference) {
    Sanctum::actingAs(purchaseOrderUserWith(['merge']));
    [$destination, $source] = mergePair();
    $stranger = purchaseOrderWithLines(PurchaseOrderStatus::Draft, 1, [
        'supplier_id' => $difference === 'supplier' ? Registry::factory()->supplier()->create()->id : $destination->supplier_id,
        'company_id' => $difference === 'company' ? Company::factory()->create()->id : $destination->company_id,
    ]);

    mergeInto($this, $destination, [$source->lines->first()->id, $stranger->lines->first()->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['line_ids.1']);

    expect($destination->fresh('lines')->lines)->toHaveCount(1)
        ->and($source->fresh('lines')->lines)->toHaveCount(2)
        ->and(PurchaseOrderMergeLog::query()->count())->toBe(0);
})->with(['supplier', 'company']);

it('AC-011: a line of the destination itself is refused with 422', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['merge']));
    [$destination] = mergePair();

    mergeInto($this, $destination, [$destination->lines->first()->id])->assertUnprocessable()->assertJsonValidationErrors(['line_ids.0']);
});

it('AC-011: a destination or a source past draft/in validation is refused with 409', function (PurchaseOrderStatus $sourceStatus, PurchaseOrderStatus $destinationStatus) {
    Sanctum::actingAs(purchaseOrderUserWith(['merge']));
    [$destination, $source] = mergePair($sourceStatus, $destinationStatus);

    mergeInto($this, $destination, [$source->lines->first()->id])->assertStatus(409)->assertJsonPath('success', false);

    expect($source->fresh('lines')->lines)->toHaveCount(2);
})->with([
    'source approved' => [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Draft],
    'destination approved' => [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Approved],
]);

it('AC-011: merging without the merge permission is refused with 403', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'update']));
    [$destination, $source] = mergePair();

    mergeInto($this, $destination, [$source->lines->first()->id])->assertForbidden();
    $this->getJson("/api/purchase-orders/{$destination->id}/merge-candidates")->assertForbidden();

    expect($source->fresh('lines')->lines)->toHaveCount(2);
});

it('rejects an invalid merge payload', function (array $payload, string $field) {
    Sanctum::actingAs(purchaseOrderUserWith(['merge']));
    [$destination, $source] = mergePair();

    $this->postJson("/api/purchase-orders/{$destination->id}/merge", $payload)->assertUnprocessable()->assertJsonValidationErrors([$field]);
})->with([
    'no lines' => [['line_ids' => []], 'line_ids'],
    'duplicate' => [['line_ids' => [1, 1]], 'line_ids.0'],
    'unknown line' => [['line_ids' => [999999]], 'line_ids.0'],
]);

it('AC-012: merge-candidates lists only editable orders of the same supplier and company, with their lines', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['merge']));
    [$destination, $source] = mergePair();
    $shared = ['supplier_id' => $destination->supplier_id, 'company_id' => $destination->company_id];
    purchaseOrderWithLines(PurchaseOrderStatus::Approved, 1, $shared);
    purchaseOrderWithLines(PurchaseOrderStatus::Draft, 1, ['company_id' => $destination->company_id]);
    purchaseOrderWithLines(PurchaseOrderStatus::Draft, 1, ['supplier_id' => $destination->supplier_id]);
    $validation = purchaseOrderWithLines(PurchaseOrderStatus::InValidation, 1, $shared);

    $response = $this->getJson("/api/purchase-orders/{$destination->id}/merge-candidates")->assertOk();

    expect(collect($response->json('data'))->pluck('id')->sort()->values()->all())->toBe([$source->id, $validation->id]);

    $candidate = collect($response->json('data'))->firstWhere('id', $source->id);

    expect($candidate)->toMatchArray([
        'code' => $source->code(),
        'description' => $source->description,
        'status' => 'draft',
        'grand_total' => '200.00',
    ])->and($candidate['lines'])->toHaveCount(2)
        ->and(array_keys($candidate['lines'][0]))->toBe(['id', 'description', 'quantity', 'unit_price', 'taxable_amount', 'total_amount']);
});

it('AC-012: merge-candidates of a non editable order answers 409', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['merge']));
    [$destination] = mergePair(destinationStatus: PurchaseOrderStatus::Approved);

    $this->getJson("/api/purchase-orders/{$destination->id}/merge-candidates")->assertStatus(409);
});

it('AC-012: merge-logs returns the history of the destination, most recent first', function () {
    $actor = purchaseOrderUserWith(['merge', 'view']);
    Sanctum::actingAs($actor);
    [$destination, $source] = mergePair();
    $first = $source->lines->first();
    $second = $source->lines->last();

    mergeInto($this, $destination, [$first->id])->assertOk();
    mergeInto($this, $destination, [$second->id])->assertOk();

    $logs = $this->getJson("/api/purchase-orders/{$destination->id}/merge-logs")->assertOk()->json('data');

    expect($logs)->toHaveCount(2)
        ->and($logs[0]['source_action'])->toBe('deleted')
        ->and($logs[0]['source_purchase_order_id'])->toBeNull()
        ->and($logs[0]['source_code'])->toBe($source->code())
        ->and($logs[0]['moved_lines'])->toHaveCount(1)
        ->and($logs[0]['moved_lines'][0]['description'])->toBe($second->description)
        ->and(array_keys($logs[0]['moved_lines'][0]))->toBe(['description', 'quantity', 'unit_price', 'taxable_amount', 'total_amount'])
        ->and($logs[0]['source_grand_total'])->toBe('100.00')
        ->and($logs[0]['performed_by'])->toBe(['id' => $actor->id, 'name' => $actor->name])
        ->and($logs[1]['source_action'])->toBe('partial')
        // the source was deleted by the second merge: the FK nulls the reference of the first log too
        ->and($logs[1]['source_purchase_order_id'])->toBeNull()
        ->and($logs[1]['source_grand_total'])->toBe('200.00')
        ->and($logs[1]['source_description'])->toBe($source->description)
        ->and($logs[1]['source_order_date'])->toBe($source->order_date->toDateString());

    $this->getJson("/api/purchase-orders/{$destination->id}")->assertOk()->assertJsonPath('data.merge_logs_count', 2);
});

it('AC-012: merge-logs requires view', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['merge']));
    [$destination] = mergePair();

    $this->getJson("/api/purchase-orders/{$destination->id}/merge-logs")->assertForbidden();
});
