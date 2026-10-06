<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Enums\InstallmentStatus;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Services\DocumentLayouts\Rendering\ValueFormatter;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The overdue part of an invoice's schedule, as the payment reminder email
 * shows it (spec 0195, D-13): the unpaid installments due before today, their
 * residual amounts and the HTML list for `{reminder.overdue_installments}`.
 * Expects `installments` to be loaded.
 */
final class InvoiceEmailOverdue
{
    /**
     * @return Collection<int, InvoiceInstallment>
     */
    public function installments(Invoice $invoice, CarbonInterface $today): Collection
    {
        return $invoice->installments
            ->filter(fn (InvoiceInstallment $installment): bool => $installment->status() !== InstallmentStatus::Paid
                && $installment->due_date->startOfDay()->lt($today->startOfDay()))
            ->values();
    }

    public function amount(Invoice $invoice, CarbonInterface $today): string
    {
        $total = $this->installments($invoice, $today)
            ->sum(fn (InvoiceInstallment $installment): float => $this->residual($installment));

        return ValueFormatter::currency($total);
    }

    /**
     * One `<li>` per overdue installment (number, due date, amount, residual).
     * A list rather than a table: EmailHtmlSanitizer (the shared rich-text
     * allow-list) drops table elements, content included. Every value is
     * escaped: the list is substituted into the body as trusted HTML.
     */
    public function html(Invoice $invoice, CarbonInterface $today): string
    {
        $items = $this->installments($invoice, $today)->map(fn (InvoiceInstallment $installment): string => '<li>'.e(implode(' - ', [
            __('invoice_emails.reminder.number').' '.$installment->sequence,
            __('invoice_emails.reminder.due_date').' '.ValueFormatter::date($installment->due_date),
            __('invoice_emails.reminder.amount').' '.ValueFormatter::currency($installment->amount),
            __('invoice_emails.reminder.residual').' '.ValueFormatter::currency($this->residual($installment)),
        ])).'</li>');

        return $items->isEmpty() ? '' : '<ul>'.$items->implode('').'</ul>';
    }

    private function residual(InvoiceInstallment $installment): float
    {
        return (float) $installment->amount - (float) ($installment->collected_amount ?? 0);
    }
}
