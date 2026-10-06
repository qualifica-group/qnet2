<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering\Invoices;

use App\Enums\InvoiceTag;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\InvoiceLine;
use App\Models\Registry;
use Illuminate\Support\Carbon;

/**
 * The deterministic, NEVER-persisted sample Invoice an `invoices` layout
 * preview renders against when no real invoice is visible (spec 0195 D-9, the
 * counterpart of the quote sample). Every relation the render reads is set
 * explicitly, so nothing is queried and nothing is saved.
 */
final class InvoiceSampleFactory
{
    public function make(): Invoice
    {
        $today = Carbon::today();

        $company = (new Company)->forceFill(['denomination' => 'Sample company S.r.l.', 'vat_number' => 'IT00000000000']);
        $company->setRelation('addresses', collect());

        $customer = (new Registry)->forceFill(['name' => 'Sample customer S.r.l.']);
        $customer->setRelation('personalData', null);

        $invoice = (new Invoice)->forceFill([
            'type' => InvoiceType::Invoice,
            'tag' => InvoiceTag::Final,
            'number' => 1,
            'year' => (int) $today->format('Y'),
            'document_date' => $today,
            'external_number' => 'SAMPLE-1',
            'external_date' => $today,
            'notes' => 'Sample invoice',
            'net_amount' => '300.00',
            'vat_amount' => '66.00',
            'total_amount' => '366.00',
        ]);

        $invoice->setRelation('company', $company);
        $invoice->setRelation('customer', $customer);
        $invoice->setRelation('paymentMethod', null);
        $invoice->setRelation('financialAccount', null);
        $invoice->setRelation('workOrder', null);
        $invoice->setRelation('quote', null);
        $invoice->setRelation('lines', collect([$this->line('Sample item A', '1.00', '100.00'), $this->line('Sample item B', '2.00', '100.00')]));
        $invoice->setRelation('installments', collect([$this->installment(1, $today, '183.00'), $this->installment(2, $today->copy()->addDays(30), '183.00')]));

        return $invoice;
    }

    private function line(string $description, string $quantity, string $unitPrice): InvoiceLine
    {
        $net = number_format((float) $quantity * (float) $unitPrice, 2, '.', '');
        $vat = number_format((float) $net * 0.22, 2, '.', '');

        $line = (new InvoiceLine)->forceFill([
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'net_amount' => $net,
            'vat_amount' => $vat,
            'total_amount' => number_format((float) $net + (float) $vat, 2, '.', ''),
        ]);
        $line->setRelation('product', null);
        $line->setRelation('vatRate', null);

        return $line;
    }

    private function installment(int $sequence, Carbon $dueDate, string $amount): InvoiceInstallment
    {
        return (new InvoiceInstallment)->forceFill([
            'sequence' => $sequence,
            'due_date' => $dueDate,
            'amount' => $amount,
            'payment_method_code' => 'MP05',
        ]);
    }
}
