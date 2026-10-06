<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering\Invoices;

use App\Enums\DocumentLayoutModule;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\InvoiceLine;
use App\Models\User;
use App\Services\DocumentLayouts\Rendering\DocumentRenderSubject;
use App\Services\DocumentLayouts\Rendering\ValueFormatter;

/**
 * The `invoices` module's render subject (spec 0195 D-5/D-6/D-7): wraps an
 * Invoice and exposes its variables and the `invoice_lines` / `installments`
 * products_table sources (keys = DocumentLayoutProductSources). Build it with ::for().
 */
final class InvoiceRenderSubject implements DocumentRenderSubject
{
    private const string SOURCE_INSTALLMENTS = 'installments';

    /**
     * Every relation the resolver and rows read (preventLazyLoading is active
     * outside production). Already-loaded relations (the in-memory preview
     * sample) are left untouched by loadMissing.
     *
     * @var array<int, string>
     */
    private const array DETAIL_RELATIONS = [
        'company.addresses.city',
        'company.addresses.province',
        'company.addresses.state',
        'company.addresses.country',
        'customer.personalData.contacts',
        'customer.personalData.addresses.city',
        'customer.personalData.addresses.province',
        'customer.personalData.addresses.state',
        'customer.personalData.addresses.country',
        'paymentMethod',
        'financialAccount',
        'workOrder',
        'quote.companySite.personalData.addresses.city',
        'quote.companySite.personalData.addresses.province',
        'quote.companySite.personalData.addresses.state',
        'quote.companySite.personalData.addresses.country',
        'quote.companySite.banks',
        'lines.product',
        'lines.vatRate',
        'installments',
    ];

    public function __construct(
        private readonly Invoice $invoice,
        private readonly InvoiceFieldResolver $fieldResolver,
    ) {}

    public static function for(Invoice $invoice): self
    {
        return app()->make(self::class, ['invoice' => $invoice]);
    }

    public function module(): DocumentLayoutModule
    {
        return DocumentLayoutModule::Invoices;
    }

    public function prepare(): void
    {
        $this->invoice->loadMissing(self::DETAIL_RELATIONS);
    }

    public function resolveVariable(string $category, string $key, User $actor): string
    {
        return $this->fieldResolver->resolve($category, $key, $this->invoice, $actor);
    }

    public function productRows(string $source): array
    {
        if ($source === self::SOURCE_INSTALLMENTS) {
            return $this->invoice->installments
                ->map(fn (InvoiceInstallment $installment): array => $this->installmentRow($installment))
                ->values()->all();
        }

        return $this->invoice->lines
            ->map(fn (InvoiceLine $line): array => $this->lineRow($line))
            ->values()->all();
    }

    /**
     * @return array<string, string>
     */
    private function lineRow(InvoiceLine $line): array
    {
        return [
            'code' => ValueFormatter::text($line->product?->code),
            'name' => ValueFormatter::text($line->product?->name),
            'description' => ValueFormatter::text($line->description),
            'quantity' => ValueFormatter::quantity($line->quantity),
            'unit_price' => ValueFormatter::currency($line->unit_price),
            'vat_rate' => ValueFormatter::vatRate($line->vatRate),
            'net_amount' => ValueFormatter::currency($line->net_amount),
            'vat_amount' => ValueFormatter::currency($line->vat_amount),
            'total_amount' => ValueFormatter::currency($line->total_amount),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function installmentRow(InvoiceInstallment $installment): array
    {
        return [
            'sequence' => (string) $installment->sequence,
            'due_date' => ValueFormatter::date($installment->due_date),
            'amount' => ValueFormatter::currency($installment->amount),
            'payment_method_code' => ValueFormatter::text($installment->payment_method_code),
            'status' => __("document_layouts.installment_status.{$installment->status()->value}"),
        ];
    }
}
