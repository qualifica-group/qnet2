<?php

use App\DataObjects\Ledger\LedgerEntry;
use App\DataObjects\Ledger\LedgerInstallmentEntry;
use App\Services\Invoices\InvoicePaymentStatusResolver;
use App\Services\Ledger\LedgerCalculator;
use App\Services\Ledger\LedgerSide;
use Carbon\CarbonImmutable;

// Spec 0266 — pure ledger arithmetic, no database: AC-003, AC-004, AC-008, AC-009, AC-010.

const LEDGER_TODAY = '2026-10-15';

function ledgerToday(): CarbonImmutable
{
    return CarbonImmutable::parse(LEDGER_TODAY);
}

function ledgerDay(int $offset): CarbonImmutable
{
    return ledgerToday()->addDays($offset);
}

/** An open installment due `$offset` days from today (negative = past). */
function ledgerOpenRow(int $id, int $offset, int $cents, int $sequence = 1): LedgerInstallmentEntry
{
    return new LedgerInstallmentEntry($id, $sequence, ledgerDay($offset), $cents, null, null);
}

/**
 * @param  array<int, LedgerInstallmentEntry>  $rows
 */
function ledgerEntry(int $id, string $date, int $totalCents, array $rows): LedgerEntry
{
    return new LedgerEntry($id, 'invoice', "{$id}/2026", null, CarbonImmutable::parse($date), null, null, null, $totalCents, $rows);
}

function ledgerCalculator(): LedgerCalculator
{
    return new LedgerCalculator(new InvoicePaymentStatusResolver);
}

it('AC-008: puts each open installment in its aging bucket and ignores the closed ones', function () {
    $offsets = [-10, -45, -75, -120, 0, 40, 90];
    $rows = [];
    foreach ($offsets as $index => $offset) {
        $rows[] = ledgerOpenRow($index + 1, $offset, 10000 * ($index + 1), $index + 1);
    }
    $rows[] = new LedgerInstallmentEntry(99, 8, ledgerDay(-5), 50000, 50000, ledgerDay(-1));
    $entry = ledgerEntry(1, '2026-01-10', 330000, $rows);

    $summary = ledgerCalculator()->section([$entry], LedgerSide::Customer, null, null, ledgerToday())['summary'];

    expect($summary['aging'])->toBe([
        'overdue_1_30' => 100.0, 'overdue_31_60' => 200.0, 'overdue_61_90' => 300.0, 'overdue_over_90' => 400.0,
        'due_0_30' => 500.0, 'due_31_60' => 600.0, 'due_over_60' => 700.0,
    ])
        ->and($summary['overdue_total'])->toBe(1000.0)
        ->and($summary['not_due_total'])->toBe(1800.0);
});

it('AC-003: documents before date_from fold into the opening balance and seed the running balance', function () {
    $old = ledgerEntry(1, '2025-12-20', 50000, [ledgerOpenRow(1, -30, 20000)]);
    $new = ledgerEntry(2, '2026-02-01', 30000, [ledgerOpenRow(2, 10, 30000)]);

    $section = ledgerCalculator()->section([$new, $old], LedgerSide::Customer, CarbonImmutable::parse('2026-01-01'), null, ledgerToday());

    expect($section['opening_balance'])->toBe(200.0)
        ->and(array_column($section['documents'], 'id'))->toBe([2])
        ->and($section['documents'][0]['running_balance'])->toBe(500.0);
});

it('AC-004 and AC-009: documents after date_to are out; the aging still counts the opening documents', function () {
    $opening = ledgerEntry(1, '2025-12-20', 20000, [ledgerOpenRow(1, -20, 20000)]);
    $inside = ledgerEntry(2, '2026-02-01', 30000, [ledgerOpenRow(2, 5, 30000)]);
    $after = ledgerEntry(3, '2026-09-01', 99900, [ledgerOpenRow(3, 5, 99900)]);

    $section = ledgerCalculator()->section([$opening, $inside, $after], LedgerSide::Customer, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-06-30'), ledgerToday());

    expect($section['summary']['invoiced_total'])->toBe(300.0)
        ->and($section['summary']['residual_total'])->toBe(500.0)
        ->and($section['summary']['residual_total'])->toBe($section['documents'][0]['running_balance'])
        ->and($section['summary']['aging']['overdue_1_30'])->toBe(200.0)
        ->and($section['summary']['aging']['due_0_30'])->toBe(300.0);
});

it('AC-010: the status follows the oldest open installment and days_late counts the delay', function (int $offset, string $status, int $daysLate) {
    $entry = ledgerEntry(1, '2026-01-10', 10000, [ledgerOpenRow(1, $offset, 10000)]);

    $document = ledgerCalculator()->section([$entry], LedgerSide::Customer, null, null, ledgerToday())['documents'][0];

    expect($document['status'])->toBe($status)
        ->and($document['installments'][0]['days_late'])->toBe($daysLate);
})->with([
    'overdue 5 days' => [-5, 'overdue', 5],
    'seriously overdue 30 days' => [-30, 'seriously_overdue', 30],
    'not due' => [3, 'not_due', 0],
]);

it('AC-010: a document with every installment closed is paid', function () {
    $entry = ledgerEntry(1, '2026-01-10', 10000, [new LedgerInstallmentEntry(1, 1, ledgerDay(-40), 10000, 10000, ledgerDay(-39))]);

    expect(ledgerCalculator()->section([$entry], LedgerSide::Customer, null, null, ledgerToday())['documents'][0]['status'])->toBe('paid');
});

it('keeps the running balance exact over many cent amounts and swaps debit and credit for the supplier', function () {
    $entries = [];
    for ($i = 1; $i <= 50; $i++) {
        $entries[] = ledgerEntry($i, '2026-02-01', 10, [ledgerOpenRow($i, 5, 10)]);
    }

    $section = ledgerCalculator()->section($entries, LedgerSide::Supplier, null, null, ledgerToday());

    expect($section['documents'][49]['running_balance'])->toBe(5.0)
        ->and($section['documents'][0]['credit'])->toBe(0.1)
        ->and($section['documents'][0]['debit'])->toBe(0.0);
});
