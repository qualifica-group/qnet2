<?php

use App\Enums\PurchaseInvoiceType;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrderLine;
use App\Services\PurchaseOrders\PurchaseOrderLineBalance;
use App\Services\PurchaseOrders\PurchaseOrderLineBillingLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function ledgerBalance(string $ordered, string $received = '0.000', bool $closed = false, string $total = '0.000', string $invoice = '0.000'): PurchaseOrderLineBalance
{
    return new PurchaseOrderLineBalance(1, $ordered, $received, $closed, $total, $invoice);
}

function ledgerLine(string $ordered = '10.000'): PurchaseOrderLine
{
    return PurchaseOrderLine::factory()->create(['quantity' => $ordered]);
}

function ledgerBill(PurchaseOrderLine $line, string $quantity, PurchaseInvoiceType $type = PurchaseInvoiceType::Invoice, ?PurchaseInvoice $document = null): PurchaseInvoiceLine
{
    $document ??= $type === PurchaseInvoiceType::Invoice ? PurchaseInvoice::factory()->create() : PurchaseInvoice::factory()->proforma()->create();

    return PurchaseInvoiceLine::factory()->create([
        'purchase_invoice_id' => $document->id,
        'purchase_order_line_id' => $line->id,
        'quantity' => $quantity,
    ]);
}

it('ceiling is the ordered quantity, or the received one once the residual is closed', function () {
    expect(ledgerBalance('10.000', '4.000')->ceiling())->toBe('10.000')
        ->and(ledgerBalance('10.000', '4.000', true)->ceiling())->toBe('4.000');
});

it('isComplete and residual follow the received quantity and the closure', function () {
    $open = ledgerBalance('10.000', '4.000');
    $full = ledgerBalance('10.000', '10.000');
    $closed = ledgerBalance('10.000', '4.000', true);

    expect($open->isComplete())->toBeFalse()
        ->and($open->residual())->toBe('6.000')
        ->and($full->isComplete())->toBeTrue()
        ->and($full->residual())->toBe('0.000')
        ->and($closed->isComplete())->toBeTrue()
        ->and($closed->residual())->toBe('0.000');
});

it('fulfillment is open, partial or complete', function () {
    expect(ledgerBalance('10.000')->fulfillment())->toBe('open')
        ->and(ledgerBalance('10.000', '0.500')->fulfillment())->toBe('partial')
        ->and(ledgerBalance('10.000', '10.000')->fulfillment())->toBe('complete')
        ->and(ledgerBalance('10.000', '0.000', true)->fulfillment())->toBe('complete');
});

it('available is the ceiling minus everything already billed, whatever the document type', function () {
    expect(ledgerBalance('10.000', '0.000', false, '4.000')->available())->toBe('6.000')
        ->and(ledgerBalance('10.000', '0.000', false, '2.000', '2.000')->available())->toBe('8.000')
        ->and(ledgerBalance('10.000', '6.000', true, '4.000')->available())->toBe('2.000')
        ->and(ledgerBalance('10.000', '6.000', true, '9.000')->available())->toBe('0.000');
});

it('toReceive is the invoiced-as-invoice quantity not yet received, within the residual', function () {
    expect(ledgerBalance('10.000', '0.000', false, '4.000', '4.000')->toReceive())->toBe('4.000')
        ->and(ledgerBalance('10.000', '3.000', false, '5.000', '5.000')->toReceive())->toBe('2.000')
        ->and(ledgerBalance('10.000', '6.000', false, '5.000', '5.000')->toReceive())->toBe('0.000')
        ->and(ledgerBalance('10.000', '0.000', false, '6.000', '0.000')->toReceive())->toBe('0.000')
        ->and(ledgerBalance('10.000', '8.000', false, '12.000', '12.000')->toReceive())->toBe('2.000')
        ->and(ledgerBalance('10.000', '10.000', false, '12.000', '12.000')->toReceive())->toBe('0.000');
});

it('forLines returns an empty collection without ids', function () {
    expect(app(PurchaseOrderLineBillingLedger::class)->forLines([]))->toBeEmpty();
});

it('forLines reads ordered, received, closure and billed quantities by document type', function () {
    $line = ledgerLine('10.500');
    $line->forceFill(['received_quantity' => '4.250'])->save();
    ledgerBill($line, '2.000', PurchaseInvoiceType::Invoice);
    ledgerBill($line, '1.500', PurchaseInvoiceType::Proforma);
    ledgerBill($line, '0.500', PurchaseInvoiceType::Invoice);
    $untouched = ledgerLine('3.000');

    $balances = app(PurchaseOrderLineBillingLedger::class)->forLines([$line->id, $untouched->id]);
    $balance = $balances[$line->id];

    expect($balances->keys()->all())->toBe([$line->id, $untouched->id])
        ->and($balance->lineId)->toBe($line->id)
        ->and($balance->ordered)->toBe('10.500')
        ->and($balance->received)->toBe('4.250')
        ->and($balance->residualClosed)->toBeFalse()
        ->and($balance->invoicedTotal)->toBe('4.000')
        ->and($balance->invoicedInvoice)->toBe('2.500')
        ->and($balances[$untouched->id]->invoicedTotal)->toBe('0.000')
        ->and($balances[$untouched->id]->received)->toBe('0.000');
});

it('forLines flags a closed residual', function () {
    $line = ledgerLine();
    $line->forceFill(['received_quantity' => '3.000', 'residual_closed_at' => now()])->save();

    $balance = app(PurchaseOrderLineBillingLedger::class)->forLines([$line->id])[$line->id];

    expect($balance->residualClosed)->toBeTrue()
        ->and($balance->ceiling())->toBe('3.000');
});

it('forLines excludes the document being edited', function () {
    $line = ledgerLine();
    $editing = PurchaseInvoice::factory()->create();
    ledgerBill($line, '3.000', PurchaseInvoiceType::Invoice, $editing);
    ledgerBill($line, '2.000', PurchaseInvoiceType::Proforma);

    $ledger = app(PurchaseOrderLineBillingLedger::class);

    expect($ledger->forLines([$line->id])[$line->id]->invoicedTotal)->toBe('5.000')
        ->and($ledger->forLines([$line->id], $editing->id)[$line->id]->invoicedTotal)->toBe('2.000')
        ->and($ledger->forLines([$line->id], $editing->id)[$line->id]->invoicedInvoice)->toBe('0.000');
});

it('forLines runs two selects whatever the number of lines, with or without lock', function () {
    $lines = collect([ledgerLine(), ledgerLine(), ledgerLine()]);
    $lines->each(fn ($line) => ledgerBill($line, '1.000'));
    $ids = $lines->pluck('id')->all();
    $ledger = app(PurchaseOrderLineBillingLedger::class);

    foreach ([false, true] as $lock) {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $balances = DB::transaction(fn () => $ledger->forLines($ids, null, $lock));
        $selects = collect(DB::getQueryLog())->filter(fn ($query) => str_starts_with(strtolower($query['query']), 'select'))->count();
        DB::disableQueryLog();

        expect($balances)->toHaveCount(3)->and($selects)->toBe(2);
    }
});

it('available reflects mixed proforma and invoice lines read from the database', function () {
    $line = ledgerLine('10.000');
    $line->forceFill(['received_quantity' => '5.000'])->save();
    ledgerBill($line, '4.000', PurchaseInvoiceType::Proforma);
    ledgerBill($line, '3.000', PurchaseInvoiceType::Invoice);

    $balance = app(PurchaseOrderLineBillingLedger::class)->forLines([$line->id])[$line->id];

    expect($balance->available())->toBe('3.000')
        ->and($balance->invoicedTotal)->toBe('7.000')
        ->and($balance->invoicedInvoice)->toBe('3.000');
});
