<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptLine;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

const RECEIPTS_TABLE_MIGRATION = 'migrations/2026_10_18_100000_create_purchase_order_receipts_tables.php';
const RECEIPTS_INDEX_MIGRATION = 'migrations/2026_10_18_100100_replace_purchase_invoice_lines_order_line_unique_with_index.php';
const RECEIPTS_BACKFILL_MIGRATION = 'migrations/2026_10_18_100200_backfill_receipts_of_fulfilled_purchase_orders.php';

it('AC-001: creates the receipt tables and the order line columns', function () {
    expect(Schema::hasColumns('purchase_order_receipts', [
        'purchase_order_id', 'receipt_date', 'delivery_note_number', 'delivery_note_date', 'notes', 'created_by',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('purchase_order_receipt_lines', ['purchase_order_receipt_id', 'purchase_order_line_id', 'quantity']))->toBeTrue()
        ->and(Schema::hasColumns('purchase_order_lines', [
            'received_quantity', 'residual_closed_at', 'residual_closed_by_id', 'residual_close_reason',
        ]))->toBeTrue();
});

it('AC-001: received_quantity defaults to zero and the new columns are not mass assignable', function () {
    $line = PurchaseOrderLine::factory()->create()->fresh();

    expect($line->received_quantity)->toBe('0.000')
        ->and($line->residual_closed_at)->toBeNull()
        ->and($line->isFillable('received_quantity'))->toBeFalse()
        ->and($line->isFillable('residual_closed_at'))->toBeFalse()
        ->and($line->isFillable('residual_closed_by_id'))->toBeFalse()
        ->and($line->isFillable('residual_close_reason'))->toBeFalse();
});

it('AC-001: relations of receipts, lines and the closure author work', function () {
    $order = purchaseOrderWithLines(PurchaseOrderStatus::Approved, 2);
    [$first, $second] = $order->lines;
    $receipt = purchaseOrderReceive($order, [$first->id => '1.000', $second->id => '0.500']);
    $closer = User::factory()->create();
    $first->forceFill(['residual_closed_by_id' => $closer->id, 'residual_closed_at' => now(), 'residual_close_reason' => 'lost'])->save();
    $first = $first->fresh();

    expect($order->receipts()->pluck('id')->all())->toBe([$receipt->id])
        ->and($receipt->lines)->toHaveCount(2)
        ->and($receipt->purchaseOrder->is($order))->toBeTrue()
        ->and($receipt->createdBy)->toBeInstanceOf(User::class)
        ->and($receipt->lines->first()->receipt->is($receipt))->toBeTrue()
        ->and($receipt->lines->first()->purchaseOrderLine->is($first))->toBeTrue()
        ->and($first->receiptLines)->toHaveCount(1)
        ->and($first->residualClosedBy->is($closer))->toBeTrue()
        ->and($first->residual_closed_at)->not->toBeNull()
        ->and($first->received_quantity)->toBe('1.000')
        ->and($first->invoiceLines)->toBeEmpty();
});

it('AC-001: a line is received once per receipt', function () {
    $order = purchaseOrderWithLines(PurchaseOrderStatus::Approved);
    $line = $order->lines->first();
    $receipt = PurchaseOrderReceipt::factory()->create(['purchase_order_id' => $order->id]);
    PurchaseOrderReceiptLine::factory()->create(['purchase_order_receipt_id' => $receipt->id, 'purchase_order_line_id' => $line->id]);

    expect(fn () => PurchaseOrderReceiptLine::factory()->create(['purchase_order_receipt_id' => $receipt->id, 'purchase_order_line_id' => $line->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('AC-001: an order line can be billed by several document lines', function () {
    $orderLine = PurchaseOrderLine::factory()->create();
    PurchaseInvoiceLine::factory()->count(2)->create(['purchase_order_line_id' => $orderLine->id]);

    expect($orderLine->invoiceLines()->count())->toBe(2);
});

it('AC-001: the migrations roll back and re-apply', function () {
    $tables = require database_path(RECEIPTS_TABLE_MIGRATION);
    $index = require database_path(RECEIPTS_INDEX_MIGRATION);
    $backfill = require database_path(RECEIPTS_BACKFILL_MIGRATION);

    $backfill->down();
    $index->down();
    $tables->down();

    expect(Schema::hasTable('purchase_order_receipts'))->toBeFalse()
        ->and(Schema::hasTable('purchase_order_receipt_lines'))->toBeFalse()
        ->and(Schema::hasColumn('purchase_order_lines', 'received_quantity'))->toBeFalse()
        ->and(Schema::hasColumn('purchase_order_lines', 'residual_closed_by_id'))->toBeFalse();

    $tables->up();
    $index->up();
    $backfill->up();

    expect(Schema::hasTable('purchase_order_receipts'))->toBeTrue()
        ->and(Schema::hasColumn('purchase_order_lines', 'received_quantity'))->toBeTrue();
});

it('AC-001: the backfill gives each fulfilled order one receipt with the ordered quantities', function () {
    $fulfilled = purchaseOrderWithLines(PurchaseOrderStatus::Fulfilled, 2);
    $fulfilled->lines->last()->forceFill(['quantity' => '2.500'])->save();
    DB::table('purchase_orders')->where('id', $fulfilled->id)->update(['updated_at' => '2026-05-20 10:30:00']);
    $partial = purchaseOrderWithLines(PurchaseOrderStatus::PartiallyFulfilled);
    $approved = purchaseOrderWithLines(PurchaseOrderStatus::Approved);

    $backfill = require database_path(RECEIPTS_BACKFILL_MIGRATION);
    $backfill->up();

    $receipts = PurchaseOrderReceipt::query()->get();
    $receipt = $receipts->first();

    expect($receipts)->toHaveCount(1)
        ->and($receipt->purchase_order_id)->toBe($fulfilled->id)
        ->and($receipt->receipt_date->toDateString())->toBe('2026-05-20')
        ->and($receipt->notes)->toBe('Ricezione generata dalla migrazione (spec 0254)')
        ->and($receipt->created_by)->toBe($fulfilled->created_by)
        ->and($receipt->lines)->toHaveCount(2)
        ->and(PurchaseOrderLine::query()->where('purchase_order_id', $fulfilled->id)->get()
            ->every(fn ($line) => $line->received_quantity === $line->quantity
                && $receipt->lines->firstWhere('purchase_order_line_id', $line->id)->quantity === $line->quantity))->toBeTrue()
        ->and(PurchaseOrderReceipt::query()->whereIn('purchase_order_id', [$partial->id, $approved->id])->exists())->toBeFalse()
        ->and(PurchaseOrderLine::query()->where('purchase_order_id', $partial->id)->value('received_quantity'))->toBe('0.000');
});

it('AC-001: the backfill rollback removes the generated receipts and resets the received quantity', function () {
    $fulfilled = purchaseOrderWithLines(PurchaseOrderStatus::Fulfilled);
    $backfill = require database_path(RECEIPTS_BACKFILL_MIGRATION);
    $backfill->up();

    expect(PurchaseOrderReceipt::query()->count())->toBe(1);

    $backfill->down();

    expect(PurchaseOrderReceipt::query()->count())->toBe(0)
        ->and(PurchaseOrderReceiptLine::query()->count())->toBe(0)
        ->and(PurchaseOrderLine::query()->where('purchase_order_id', $fulfilled->id)->value('received_quantity'))->toBe('0.000');
});
