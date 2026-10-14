<?php

use App\Models\PurchaseInvoiceLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * The index of the first query matching the pattern, or null.
 *
 * @param  array<int, string>  $queries
 */
function firstQueryMatching(array $queries, string $pattern): ?int
{
    foreach ($queries as $index => $sql) {
        if (preg_match($pattern, $sql) === 1) {
            return $index;
        }
    }

    return null;
}

it('AC-012: a receipt takes the locks in order: the order, its lines, then the document lines', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable();
    PurchaseInvoiceLine::factory()->create(['purchase_order_line_id' => $order->lines->first()->id, 'quantity' => '1.000']);

    // SQLite drops FOR UPDATE: the order of the reads of the three tables is the order of the locks
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->postJson("/api/purchase-orders/{$order->id}/receipts", purchaseOrderReceiptPayload($order))->assertCreated();

    $orderRead = firstQueryMatching($queries, '/from "purchase_orders" where "purchase_orders"."id" = \?/');
    $linesRead = firstQueryMatching($queries, '/from "purchase_order_lines" where "id" in/');
    $documentLinesRead = firstQueryMatching($queries, '/from "purchase_invoice_lines" where "purchase_order_line_id" in/');

    expect($orderRead)->not->toBeNull()
        ->and($linesRead)->not->toBeNull()
        ->and($documentLinesRead)->not->toBeNull()
        ->and($orderRead)->toBeLessThan($linesRead)
        ->and($linesRead)->toBeLessThan($documentLinesRead);
});

it('AC-012: the residual is re-checked after the lock, so two receipts never exceed the ordered quantity', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable(quantity: '10.000');
    $line = $order->lines->first();
    $url = "/api/purchase-orders/{$order->id}/receipts";

    $this->postJson($url, purchaseOrderReceiptPayload($order, [$line->id => '7']))->assertCreated();
    $this->postJson($url, purchaseOrderReceiptPayload($order, [$line->id => '7']))->assertUnprocessable()->assertJsonValidationErrors(['lines.0.quantity']);
    $this->postJson($url, purchaseOrderReceiptPayload($order, [$line->id => '3']))->assertCreated();

    expect($line->fresh()->received_quantity)->toBe('10.000');
});

it('AC-012: the receipt and its quantities are written in one transaction', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $order = purchaseOrderReceivable();
    [$first, $second] = $order->lines;
    $level = null;

    DB::listen(function ($query) use (&$level): void {
        if (str_contains($query->sql, 'insert into "purchase_order_receipts"')) {
            $level = DB::transactionLevel();
        }
    });

    $this->postJson("/api/purchase-orders/{$order->id}/receipts", purchaseOrderReceiptPayload($order, [$first->id => '1', $second->id => '1']))->assertCreated();

    // RefreshDatabase wraps the test in one transaction: the service adds a savepoint on top
    expect($level)->toBeGreaterThan(1);
});
