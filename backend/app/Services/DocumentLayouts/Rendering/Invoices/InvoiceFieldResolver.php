<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\User;
use App\Services\DocumentLayouts\Rendering\OrganizationFieldResolver;
use App\Services\DocumentLayouts\Rendering\PartyFieldResolver;
use App\Services\DocumentLayouts\Rendering\ValueFormatter;
use App\Support\AddressLabel;
use Illuminate\Support\Carbon;

/**
 * Resolves the `invoices` module's variable categories (spec 0195 D-7) for an
 * Invoice. The customer and the issuing site reuse the quote resolvers
 * (PartyFieldResolver / OrganizationFieldResolver), so masking and address
 * formatting are shared, not duplicated. An unknown category/key is ''.
 */
final class InvoiceFieldResolver
{
    /** `customer.*` keys that map 1:1 onto the Registry-backed `client.*` resolution. */
    private const array CUSTOMER_REGISTRY_KEYS = ['name', 'address', 'vat_number', 'tax_code', 'sdi_code', 'email'];

    public function __construct(
        private readonly PartyFieldResolver $partyFields,
        private readonly OrganizationFieldResolver $organizationFields,
    ) {}

    public function resolve(string $category, string $key, Invoice $invoice, User $actor): string
    {
        return match ($category) {
            'invoice' => $this->invoice($key, $invoice),
            'customer' => $this->customer($key, $invoice, $actor),
            'company' => $this->company($key, $invoice),
            'company_site' => $this->organizationFields->siteField($key, $invoice->quote?->companySite) ?? '',
            'payment' => $this->payment($key, $invoice),
            'totals' => $this->totals($key, $invoice),
            'work_order' => $this->workOrder($key, $invoice),
            'quote' => $key === 'code' ? ValueFormatter::text($invoice->quote?->code) : '',
            'document' => $this->document($key, $actor),
            default => '',
        };
    }

    private function invoice(string $key, Invoice $invoice): string
    {
        return match ($key) {
            'number_label' => $invoice->number === null ? '' : "{$invoice->number}/{$invoice->year}",
            'type_label' => $invoice->type?->label() ?? '',
            'document_date' => ValueFormatter::date($invoice->document_date),
            'external_number' => ValueFormatter::text($invoice->external_number),
            'external_date' => ValueFormatter::date($invoice->external_date),
            'notes' => ValueFormatter::text($invoice->notes),
            'tag_label' => $invoice->tag?->label() ?? '',
            default => '',
        };
    }

    private function customer(string $key, Invoice $invoice, User $actor): string
    {
        if ($key === 'pec') {
            return $this->partyFields->registryPec($invoice->customer);
        }

        if (! in_array($key, self::CUSTOMER_REGISTRY_KEYS, true)) {
            return '';
        }

        return $this->partyFields->registryField($key, $invoice->customer, $actor) ?? '';
    }

    private function company(string $key, Invoice $invoice): string
    {
        $company = $invoice->company;

        return match ($key) {
            'name' => ValueFormatter::text($company?->denomination),
            'vat_number' => ValueFormatter::text($company?->vat_number),
            'address' => AddressLabel::singleLine($company?->primaryAddress),
            default => '',
        };
    }

    private function payment(string $key, Invoice $invoice): string
    {
        return match ($key) {
            'method_name' => ValueFormatter::text($invoice->paymentMethod?->name),
            'payment_instructions' => ValueFormatter::text($invoice->paymentMethod?->payment_instructions),
            'bank_name' => ValueFormatter::text($invoice->financialAccount?->name),
            'iban' => ValueFormatter::text($invoice->financialAccount?->iban),
            default => '',
        };
    }

    private function totals(string $key, Invoice $invoice): string
    {
        $collected = $invoice->installments->sum(static fn (InvoiceInstallment $installment): float => (float) $installment->collected_amount);

        return match ($key) {
            'net' => ValueFormatter::currency($invoice->net_amount),
            'vat' => ValueFormatter::currency($invoice->vat_amount),
            'total' => ValueFormatter::currency($invoice->total_amount),
            'collected' => ValueFormatter::currency($collected),
            'residual' => ValueFormatter::currency((float) $invoice->total_amount - $collected),
            default => '',
        };
    }

    private function workOrder(string $key, Invoice $invoice): string
    {
        return match ($key) {
            'code' => ValueFormatter::text($invoice->workOrder?->code),
            'title' => ValueFormatter::text($invoice->workOrder?->title),
            default => '',
        };
    }

    private function document(string $key, User $actor): string
    {
        return match ($key) {
            'generated_at' => ValueFormatter::date(Carbon::now()),
            'generated_by' => ValueFormatter::text($actor->name),
            default => '',
        };
    }
}
