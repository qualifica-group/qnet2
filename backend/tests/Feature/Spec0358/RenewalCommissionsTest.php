<?php

use App\Enums\CommissionRecipientRole;
use App\Enums\InvoiceType;
use App\Models\CommissionAccrual;
use App\Models\Company;
use App\Models\WorkOrder;
use App\Services\ManagementControl\WorkOrderFigureSources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0358 MT-03: commissions accrue per commessa (D-8, AC-018) and the income
 * statement reads them per commessa (D-9, AC-019).
 */
uses(RefreshDatabase::class);

/** COM-A (period 0) and COM-B (period 1) invoice the same line (net 1000, commission 100) in full and collect it. */
function sameLineInvoicedInTwoCommesse(): array
{
    $line = accrualQuoteLine('1000.00');
    accrualCommission($line, '100.00');
    $a = WorkOrder::factory()->create(['quote_id' => $line->quote_id]);
    $b = WorkOrder::factory()->create(['quote_id' => $line->quote_id]);
    attachLineInPeriod($a, $line, 0);
    attachLineInPeriod($b, $line, 1);

    $invoiceA = accrualInvoice([[$line, '1000.00']], ['1000.00']);
    $invoiceA->update(['work_order_id' => $a->id, 'type' => InvoiceType::Invoice]);
    accrualCollect(accrualInstallment($invoiceA), '1000.00', '2026-04-20');
    $afterA = CommissionAccrual::query()->orderBy('id')->get(['id', 'amount'])->toArray();

    $invoiceB = accrualInvoice([[$line, '1000.00']], ['1000.00'], '2026-05-01');
    $invoiceB->update(['work_order_id' => $b->id, 'type' => InvoiceType::Invoice]);
    accrualCollect(accrualInstallment($invoiceB), '1000.00', '2026-06-20');

    return [$invoiceA, $invoiceB, $a, $b, $afterA];
}

it('AC-018: the commission accrues in full on each commessa and COM-A is untouched by COM-B', function () {
    [$invoiceA, $invoiceB, , , $afterA] = sameLineInvoicedInTwoCommesse();

    expect(accrualSum($invoiceA))->toBe('100.00')
        ->and(accrualSum($invoiceB))->toBe('100.00')
        ->and(CommissionAccrual::query()->where('invoice_id', $invoiceA->id)->orderBy('id')->get(['id', 'amount'])->toArray())->toEqual($afterA);
});

it('AC-019: commission_actual of the income statement is 100 per commessa', function () {
    Sanctum::actingAs(mcActor());
    [, , $a, $b] = sameLineInvoicedInTwoCommesse();
    $a->update(['start_date' => '2026-03-10']);
    $b->update(['start_date' => '2026-03-10']);
    $a->quote->update(['company_id' => Company::factory()->create()->id]);

    $figures = app(WorkOrderFigureSources::class)->collect([$a->id, $b->id])['figures'];

    expect($figures[$a->id]['commission_actual'])->toBe(10000)
        ->and($figures[$b->id]['commission_actual'])->toBe(10000);
});

it('AC-018: invoices of the same commessa still share the commission, capped at the line net', function () {
    $line = accrualQuoteLine('1000.00');
    accrualCommission($line, '100.00', CommissionRecipientRole::Commercial);
    $a = WorkOrder::factory()->create(['quote_id' => $line->quote_id]);
    attachLineInPeriod($a, $line, 0);
    $first = accrualInvoice([[$line, '400.00']], ['400.00']);
    $second = accrualInvoice([[$line, '600.00']], ['600.00'], '2026-04-01');
    $first->update(['work_order_id' => $a->id]);
    $second->update(['work_order_id' => $a->id]);

    accrualCollect(accrualInstallment($first), '400.00');
    accrualCollect(accrualInstallment($second), '600.00');

    expect(accrualSum($first))->toBe('40.00')->and(accrualSum($second))->toBe('60.00');
});
