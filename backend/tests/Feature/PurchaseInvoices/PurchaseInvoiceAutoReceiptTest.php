<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * An invoice from purchase orders receives what it bills and is not received
 * yet (spec 0254, D-12); a proforma does not.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(purchaseInvoiceUserWith(['viewAny', 'view', 'create', 'update', 'delete', 'downgrade']));
});

/**
 * Two orders of the payload parties, every line of 10 units at 5.00:
 * order A: line 0 not received, line 1 half received; order B: line 0 fully received, line 1 not received.
 *
 * @return array{0: array<string, mixed>, 1: PurchaseOrder, 2: PurchaseOrder}
 */
function autoReceiptScenario(): array
{
    $payload = purchaseInvoicePayload(['document_type' => 'invoice', 'document_number' => 'FT-77', 'document_date' => '2026-09-30']);
    $orders = [];

    foreach ([0, 1] as $unused) {
        $order = invoiceableOrderOf($payload, PurchaseOrderStatus::SentToSupplier, 2);
        $order->lines->each(fn (PurchaseOrderLine $line) => $line->update(['quantity' => '10.000', 'unit_price' => '5.00']));
        $orders[] = $order->fresh('lines');
    }

    [$a, $b] = $orders;
    purchaseOrderReceive($a, [$a->lines[1]->id => '5']);
    purchaseOrderReceive($b, [$b->lines[0]->id => '10']);

    return [$payload, $a->fresh('lines'), $b->fresh('lines')];
}

/** @return array<string, mixed> */
function autoReceiptLine(PurchaseOrderLine $line, string $quantity): array
{
    return [...orderLinePayload($line), 'quantity' => $quantity, 'unit_price' => '5.00'];
}

/** @return array<int, array<string, mixed>> the billing rows of autoReceiptScenario(): all of A, and B */
function autoReceiptRows(PurchaseOrder $a, PurchaseOrder $b): array
{
    return [
        autoReceiptLine($a->lines[0], '10'),
        autoReceiptLine($a->lines[1], '10'),
        autoReceiptLine($b->lines[0], '10'),
        autoReceiptLine($b->lines[1], '4'),
    ];
}

it('AC-016: an invoice on two orders creates one receipt per order that needs it, with the missing quantities only', function () {
    [$payload, $a, $b] = autoReceiptScenario();
    $user = auth()->user();

    $id = $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => autoReceiptRows($a, $b)])->assertCreated()->json('data.id');

    $receiptA = PurchaseOrderReceipt::query()->where('purchase_order_id', $a->id)->where('purchase_invoice_id', $id)->sole();
    $receiptB = PurchaseOrderReceipt::query()->where('purchase_order_id', $b->id)->where('purchase_invoice_id', $id)->sole();

    expect(PurchaseOrderReceipt::query()->where('purchase_invoice_id', $id)->count())->toBe(2)
        ->and($receiptA->purchase_invoice_id)->toBe($id)
        ->and($receiptA->receipt_date->toDateString())->toBe('2026-09-30')
        ->and($receiptA->delivery_note_number)->toBeNull()
        ->and($receiptA->notes)->toBe(__('purchase_invoices.receipt_note', ['number' => 'FT-77']))
        ->and($receiptA->created_by)->toBe($user->id)
        ->and($receiptA->lines->pluck('quantity', 'purchase_order_line_id')->all())
        ->toBe([$a->lines[0]->id => '10.000', $a->lines[1]->id => '5.000'])
        ->and($receiptB->purchase_invoice_id)->toBe($id)
        ->and($receiptB->lines->pluck('quantity', 'purchase_order_line_id')->all())->toBe([$b->lines[1]->id => '4.000'])
        ->and($a->fresh('lines')->lines->pluck('received_quantity')->all())->toBe(['10.000', '10.000'])
        ->and($b->fresh('lines')->lines->pluck('received_quantity')->all())->toBe(['10.000', '4.000'])
        ->and($a->fresh()->status)->toBe(PurchaseOrderStatus::Fulfilled)
        ->and($b->fresh()->status)->toBe(PurchaseOrderStatus::PartiallyFulfilled);
});

it('AC-016: a proforma on the same lines receives nothing', function () {
    [$payload, $a, $b] = autoReceiptScenario();

    $this->postJson('/api/purchase-invoices', [...$payload, 'document_type' => 'proforma', 'document_number' => null, 'lines' => autoReceiptRows($a, $b)])->assertCreated();

    expect(PurchaseOrderReceipt::query()->where('purchase_invoice_id', '!=', null)->count())->toBe(0)
        ->and($a->fresh('lines')->lines->pluck('received_quantity')->all())->toBe(['0.000', '5.000']);
});

it('AC-016: an invoice on lines already received creates no receipt', function () {
    [$payload, , $b] = autoReceiptScenario();
    $receipts = PurchaseOrderReceipt::query()->count();

    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [autoReceiptLine($b->lines[0], '10')]])->assertCreated();

    expect(PurchaseOrderReceipt::query()->count())->toBe($receipts);
});

it('AC-016: the receipt of an existing invoice cannot be cancelled, after the downgrade to proforma it can', function () {
    [$payload, $a, $b] = autoReceiptScenario();
    $id = $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => autoReceiptRows($a, $b)])->assertCreated()->json('data.id');
    $receipt = PurchaseOrderReceipt::query()->where('purchase_order_id', $b->id)->where('purchase_invoice_id', $id)->sole();
    $invoiceUser = auth()->user();

    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $this->deleteJson("/api/purchase-orders/{$b->id}/receipts/{$receipt->id}")->assertStatus(409);

    Sanctum::actingAs($invoiceUser);
    $this->patchJson("/api/purchase-invoices/{$id}/details", ['document_number' => null])
        ->assertOk()->assertJsonPath('data.document_type', 'proforma');

    expect(PurchaseOrderReceipt::query()->whereKey($receipt->id)->exists())->toBeTrue()
        ->and($b->fresh('lines')->lines[1]->received_quantity)->toBe('4.000');

    Sanctum::actingAs(purchaseOrderUserWith(['view', 'receive']));
    $this->deleteJson("/api/purchase-orders/{$b->id}/receipts/{$receipt->id}")->assertOk();

    expect($b->fresh('lines')->lines[1]->received_quantity)->toBe('0.000');
});

it('AC-016: the receipt needs only the passive-document permission, not purchase-orders.receive', function () {
    [$payload, $a, $b] = autoReceiptScenario();
    $user = purchaseInvoiceUserWith(['create']);
    Sanctum::actingAs($user);

    expect($user->can('purchase-orders.receive'))->toBeFalse();

    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => autoReceiptRows($a, $b)])->assertCreated();

    expect(PurchaseOrderReceipt::query()->where('created_by', $user->id)->count())->toBe(2);
});

it('AC-016: an order that cannot receive refuses the invoice on the first row of that order, nothing is written', function () {
    [$payload, $a, $b] = autoReceiptScenario();
    PurchaseOrder::query()->whereKey($b->id)->update(['status' => PurchaseOrderStatus::Fulfilled]);
    $receipts = PurchaseOrderReceipt::query()->count();

    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => autoReceiptRows($a, $b)])
        ->assertUnprocessable()
        ->assertJsonPath('errors', ['lines.2.purchase_order_line_id' => [__('purchase_invoices.order_not_receivable')]]);

    expect(PurchaseInvoice::query()->count())->toBe(0)
        ->and(PurchaseOrderReceipt::query()->count())->toBe($receipts)
        ->and($a->fresh('lines')->lines->pluck('received_quantity')->all())->toBe(['0.000', '5.000'])
        ->and($a->fresh()->status)->toBe(PurchaseOrderStatus::SentToSupplier);
});

it('AC-016: the order receipts list shows the invoice that generated each receipt', function () {
    [$payload, $a, $b] = autoReceiptScenario();
    $id = $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => autoReceiptRows($a, $b)])->assertCreated()->json('data.id');

    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    $rows = $this->getJson("/api/purchase-orders/{$a->id}/receipts")->assertOk()->json('data');

    expect(collect($rows)->pluck('purchase_invoice')->all())
        ->toContain(['id' => $id, 'document_number' => 'FT-77'])
        ->toContain(null);
});

it('AC-012: the auto receipt takes the order locks first, then the order lines, then writes the receipt', function () {
    [$payload, $a, $b] = autoReceiptScenario();
    $events = [];
    DB::listen(function ($query) use (&$events): void {
        if (str_contains($query->sql, 'from "purchase_orders" where "id" in')) {
            $events[] = str_contains($query->sql, 'order by "id" asc') ? 'orders_by_id' : 'orders';
        } elseif (str_contains($query->sql, '"received_quantity"') && str_contains($query->sql, 'from "purchase_order_lines"')) {
            $events[] = 'order_lines';
        } elseif (str_contains($query->sql, 'insert into "purchase_order_receipts"')) {
            $events[] = 'receipt';
        }
    });

    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => autoReceiptRows($a, $b)])->assertCreated();

    $firstReceipt = array_search('receipt', $events, true);
    $lastOrderLock = array_key_last(array_filter($events, fn (string $event): bool => $event === 'orders_by_id'));
    $firstOrderLines = array_search('order_lines', $events, true);

    expect($firstReceipt)->not->toBeFalse()
        ->and($firstOrderLines)->toBeLessThan($firstReceipt)
        ->and(array_search('orders_by_id', $events, true))->toBeLessThan($firstOrderLines)
        ->and($lastOrderLock)->toBeLessThan($firstReceipt)
        ->and(count(array_keys($events, 'receipt', true)))->toBe(2);
});
