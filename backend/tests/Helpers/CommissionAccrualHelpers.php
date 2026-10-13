<?php

declare(strict_types=1);

use App\Enums\CommissionRecipientRole;
use App\Models\CommissionAccrual;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\InvoiceLine;
use App\Models\QuoteLine;
use App\Models\QuoteLineCommission;
use App\Models\Referent;
use App\Services\Invoices\InvoiceCollectionService;

if (! function_exists('accrualQuoteLine')) {
    function accrualQuoteLine(string $net = '1000.00'): QuoteLine
    {
        return QuoteLine::factory()->create(['net_amount' => $net, 'total_amount' => $net]);
    }
}

if (! function_exists('accrualCommission')) {
    /**
     * A commission of the quote line; the recipient defaults to a new referent.
     *
     * @param  array<string, mixed>  $attributes
     */
    function accrualCommission(QuoteLine $line, string $amount, CommissionRecipientRole $role = CommissionRecipientRole::Commercial, array $attributes = []): QuoteLineCommission
    {
        return QuoteLineCommission::factory()->create($attributes + [
            'quote_line_id' => $line->id,
            'recipient_role' => $role,
            'recipient_type' => 'referent',
            'recipient_id' => Referent::factory()->create()->id,
            'calculated_amount' => $amount,
        ]);
    }
}

if (! function_exists('accrualInvoice')) {
    /**
     * An invoice whose lines are `[QuoteLine, net]` pairs and whose schedule is
     * the given installment amounts (the total is their sum, all net: no VAT).
     *
     * @param  array<int, array{0: QuoteLine, 1: string}>  $lines
     * @param  array<int, string>  $installments
     */
    function accrualInvoice(array $lines, array $installments, string $date = '2026-03-01'): Invoice
    {
        $total = array_reduce($installments, fn (string $sum, string $amount): string => bcadd($sum, $amount, 2), '0.00');
        $invoice = Invoice::factory()->create([
            'document_date' => $date,
            'net_amount' => $total,
            'vat_amount' => '0.00',
            'total_amount' => $total,
        ]);

        foreach ($lines as $index => [$line, $net]) {
            InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'quote_line_id' => $line->id, 'net_amount' => $net, 'sort_order' => $index]);
        }

        foreach ($installments as $index => $amount) {
            InvoiceInstallment::factory()->create(['invoice_id' => $invoice->id, 'sequence' => $index + 1, 'amount' => $amount]);
        }

        return $invoice;
    }
}

if (! function_exists('accrualCollect')) {
    /**
     * Collect an installment through the real service (the sync hook runs with it).
     *
     * @param  array<string, string>  $extra  residual_mode / residual_due_date
     */
    function accrualCollect(InvoiceInstallment $installment, string $amount, string $at = '2026-04-20', array $extra = []): Invoice
    {
        return app(InvoiceCollectionService::class)->record($installment, ['collected_amount' => $amount, 'collected_at' => $at] + $extra);
    }
}

if (! function_exists('accrualSum')) {
    /** Sum of the ledger amounts of an invoice (optionally one recipient role). */
    function accrualSum(Invoice $invoice, ?CommissionRecipientRole $role = null): string
    {
        return CommissionAccrual::query()->where('invoice_id', $invoice->id)
            ->when($role !== null, fn ($query) => $query->where('recipient_role', $role->value))
            ->get()->reduce(fn (string $sum, CommissionAccrual $row): string => bcadd($sum, (string) $row->amount, 2), '0.00');
    }
}

if (! function_exists('accrualRows')) {
    /**
     * @return array<int, string> "TYPE:amount:accrued_on" in insertion order
     */
    function accrualRows(Invoice $invoice): array
    {
        return CommissionAccrual::query()->where('invoice_id', $invoice->id)->orderBy('id')
            ->get()->map(fn (CommissionAccrual $row): string => "{$row->type->value}:{$row->amount}:{$row->accrued_on->toDateString()}")->all();
    }
}

if (! function_exists('accrualInstallment')) {
    /** The installment at the position (0-based) of the schedule. */
    function accrualInstallment(Invoice $invoice, int $position = 0): InvoiceInstallment
    {
        return $invoice->installments()->orderBy('sequence')->get()->values()->get($position);
    }
}
