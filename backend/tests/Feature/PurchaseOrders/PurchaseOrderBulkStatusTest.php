<?php

use App\Enums\PurchaseOrderStatus as Status;
use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const BULK_URL = '/api/purchase-orders/bulk-status';

/**
 * @param  array<int, PurchaseOrder>  $orders
 */
function postBulk(object $test, array $orders, string $to, bool $dryRun = false): TestResponse
{
    return $test->postJson(BULK_URL, [
        'ids' => array_map(fn (PurchaseOrder $order): int => $order->id, $orders),
        'to_status' => $to,
        'dry_run' => $dryRun,
    ]);
}

it('AC-001: anonymous is 401; without view, or with neither changeStatus nor approve, it is 403', function () {
    $order = purchaseOrderWithLines();

    postBulk($this, [$order], 'in_validation')->assertUnauthorized();

    Sanctum::actingAs(purchaseOrderUserWith(['view', 'update']));
    postBulk($this, [$order], 'in_validation')->assertForbidden();

    Sanctum::actingAs(purchaseOrderUserWith(['changeStatus', 'approve']));
    postBulk($this, [$order], 'in_validation')->assertForbidden();
    expect($order->fresh()->status)->toBe(Status::Draft);
});

it('AC-001: ids and to_status are validated', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'changeStatus']));
    $order = purchaseOrderWithLines();
    config()->set('purchase-orders.bulk_status_max', 2);

    $this->postJson(BULK_URL, ['ids' => [], 'to_status' => 'draft', 'dry_run' => false])->assertUnprocessable()->assertJsonValidationErrors(['ids']);
    $this->postJson(BULK_URL, ['ids' => [$order->id, 999999], 'to_status' => 'draft', 'dry_run' => false])->assertUnprocessable()->assertJsonValidationErrors(['ids.1']);
    $this->postJson(BULK_URL, ['ids' => [$order->id, $order->id, $order->id], 'to_status' => 'draft', 'dry_run' => false])->assertUnprocessable()->assertJsonValidationErrors(['ids']);
    $this->postJson(BULK_URL, ['ids' => [$order->id], 'to_status' => 'bogus', 'dry_run' => false])->assertUnprocessable()->assertJsonValidationErrors(['to_status']);
    $this->postJson(BULK_URL, ['ids' => [$order->id], 'to_status' => 'fulfilled', 'dry_run' => false])->assertUnprocessable()->assertJsonValidationErrors(['to_status']);
    $this->postJson(BULK_URL, ['ids' => [$order->id], 'to_status' => 'partially_fulfilled', 'dry_run' => false])->assertUnprocessable()->assertJsonValidationErrors(['to_status']);
    $this->postJson(BULK_URL, ['ids' => [$order->id], 'to_status' => 'draft'])->assertUnprocessable()->assertJsonValidationErrors(['dry_run']);
});

it('AC-002: dry run reports 3 changed and 1 skipped without writing, then the apply moves the 3', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'changeStatus']));
    $drafts = [purchaseOrderWithLines(), purchaseOrderWithLines(), purchaseOrderWithLines()];
    $cancelled = purchaseOrderWithLines(Status::Cancelled);
    $all = [...$drafts, $cancelled];

    $preview = postBulk($this, $all, 'in_validation', true)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.applied', false)
        ->assertJsonPath('data.to_status', 'in_validation')
        ->assertJsonPath('data.total', 4)
        ->assertJsonCount(3, 'data.changed')
        ->assertJsonCount(1, 'data.skipped')
        ->assertJsonPath('data.skipped.0.id', $cancelled->id)
        ->assertJsonPath('data.skipped.0.code', $cancelled->code());

    expect($preview->json('data.skipped.0.reason'))->toBeString()->not->toBe('');
    expect(PurchaseOrder::query()->where('status', Status::Draft)->count())->toBe(3);

    postBulk($this, $all, 'in_validation')
        ->assertOk()
        ->assertJsonPath('data.applied', true)
        ->assertJsonCount(3, 'data.changed')
        ->assertJsonCount(1, 'data.skipped');

    foreach ($drafts as $order) {
        expect($order->fresh()->status)->toBe(Status::InValidation);
    }
    expect($cancelled->fresh()->status)->toBe(Status::Cancelled);
});

it('AC-003: towards approved changeStatus alone skips everything, approve moves the allowed ones', function () {
    $draft = purchaseOrderWithLines();
    $cancelled = purchaseOrderWithLines(Status::Cancelled);

    Sanctum::actingAs(purchaseOrderUserWith(['view', 'changeStatus']));
    $denied = postBulk($this, [$draft, $cancelled], 'approved')
        ->assertOk()
        ->assertJsonCount(0, 'data.changed')
        ->assertJsonCount(2, 'data.skipped');
    expect($denied->json('data.skipped.0.reason'))->toBe(__('This action is unauthorized.'));
    expect($draft->fresh()->status)->toBe(Status::Draft);

    Sanctum::actingAs(purchaseOrderUserWith(['view', 'approve']));
    postBulk($this, [$draft, $cancelled], 'approved', true)->assertJsonCount(1, 'data.changed');
    postBulk($this, [$draft, $cancelled], 'approved')
        ->assertOk()
        ->assertJsonPath('data.changed.0.id', $draft->id)
        ->assertJsonPath('data.skipped.0.id', $cancelled->id);

    expect($draft->fresh()->status)->toBe(Status::Approved)
        ->and($cancelled->fresh()->status)->toBe(Status::Cancelled);
});

it('AC-004: receipts, same status and duplicates are skipped or counted once, and cancelling releases the request lines', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'changeStatus']));

    $received = purchaseOrderWithLines(Status::SentToSupplier);
    purchaseOrderReceive($received, [$received->lines->first()->id => '1']);
    $same = purchaseOrderWithLines(Status::Cancelled);

    $request = purchaseRequestWithLines([LineStatus::Ordered], [], ['oda_reference' => '9/2026']);
    $linked = purchaseOrderWithLines(Status::Approved);
    $linked->lines->first()->forceFill(['purchase_request_line_id' => $request->lines->first()->id])->save();

    $response = postBulk($this, [$received, $same, $linked, $linked], 'cancelled', true)
        ->assertOk()
        ->assertJsonPath('data.total', 3)
        ->assertJsonCount(1, 'data.changed')
        ->assertJsonCount(2, 'data.skipped');
    expect($response->json('data.skipped.0.reason'))->toBe(__('purchase_orders.has_receipts'));

    $applied = postBulk($this, [$received, $same, $linked, $linked], 'cancelled')
        ->assertOk()
        ->assertJsonPath('data.changed.0.id', $linked->id)
        ->assertJsonCount(2, 'data.skipped');
    expect($applied->json('data.skipped.1.reason'))->toBe(__('purchase_orders.bulk_already_in_status'));

    expect($received->fresh()->status)->toBe(Status::SentToSupplier)
        ->and($linked->fresh()->status)->toBe(Status::Cancelled)
        ->and($linked->lines->first()->fresh()->purchase_request_line_id)->toBeNull()
        ->and($request->lines->first()->fresh()->status)->toBe(LineStatus::Approved);
});
