<?php

namespace App\Http\Requests\Invoices;

use App\Enums\DocumentLayoutModule;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * InvoiceWritePayload (spec 0194), shared by issue (POST) and edit (PUT, or
 * PATCH since spec 0261: the keys not sent take their stored value, lines
 * included, so the service still runs its one full update).
 * Authorization stays in the controller via the Policies; business rules that
 * need the database (bank of the company, lines of the work order, total > 0)
 * live in InvoiceService.
 */
class InvoiceWriteRequest extends FormRequest
{
    public const int NOTES_MAX_LENGTH = 5000;

    public const int MAX_LINES = 200;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'document_date' => ['required', 'date'],
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'customer_registry_id' => ['required', 'integer', Rule::exists('registries', 'id')],
            // Active methods only; the one already on the invoice stays valid even if deactivated since.
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where(
                fn ($methods) => $methods->where('is_active', true)->orWhere('id', $this->storedInvoice()?->payment_method_id),
            )],
            'financial_account_id' => ['nullable', 'integer', Rule::exists('financial_accounts', 'id')],
            'notes' => ['nullable', 'string', 'max:'.self::NOTES_MAX_LENGTH],
            'internal_note' => ['nullable', 'string', 'max:'.self::NOTES_MAX_LENGTH],
            'invoice_tag_id' => self::invoiceTagRules($this->route('invoice')),
            'layout_id' => ['nullable', 'integer', Rule::exists('document_layouts', 'id')->where('module', DocumentLayoutModule::Invoices->value)->where(
                fn ($layouts) => $layouts->where('is_active', true)->orWhere('id', $this->storedInvoice()?->layout_id),
            )],
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.quote_line_id' => ['nullable', 'integer'],
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'lines.*.unit_price' => ['required', 'numeric', 'decimal:0,2', 'between:-9999999999,9999999999'],
            'lines.*.vat_rate_id' => ['required', 'integer', Rule::exists('vat_rates', 'id')],
        ];
    }

    /**
     * Spec 0261, D-1: a PATCH sends only what changed; the rest, lines
     * included, is the stored document, so validation and the service see the
     * whole invoice exactly as the full edit would send it.
     */
    protected function prepareForValidation(): void
    {
        $invoice = $this->storedInvoice();

        if ($invoice === null || ! $this->isMethod('PATCH')) {
            return;
        }

        $stored = [
            'document_date' => $invoice->document_date?->format('Y-m-d'),
            'company_id' => $invoice->company_id,
            'customer_registry_id' => $invoice->customer_registry_id,
            'payment_method_id' => $invoice->payment_method_id,
            'financial_account_id' => $invoice->financial_account_id,
            'notes' => $invoice->notes,
            'internal_note' => $invoice->internal_note,
            'invoice_tag_id' => $invoice->invoice_tag_id,
            'layout_id' => $invoice->layout_id,
        ];

        if (! $this->has('lines')) {
            $stored['lines'] = $invoice->lines()->get()->map(static fn (InvoiceLine $line): array => [
                'quote_line_id' => $line->quote_line_id,
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'vat_rate_id' => $line->vat_rate_id,
            ])->all();
        }

        $this->merge(array_diff_key($stored, $this->all()));
    }

    private function storedInvoice(): ?Invoice
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice ? $invoice : null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['lines.*.unit_price.decimal' => __('invoices.unit_price_decimals')];
    }

    /**
     * Tag rule shared with the details request: an active tag, or the one the
     * invoice already carries even if deactivated since.
     *
     * @return array<int, mixed>
     */
    public static function invoiceTagRules(mixed $invoice): array
    {
        $currentTagId = $invoice instanceof Invoice ? $invoice->invoice_tag_id : null;

        return ['nullable', 'integer', Rule::exists('invoice_tags', 'id')->where(
            fn ($tags) => $tags->where('is_active', true)->orWhere('id', $currentTagId),
        )];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->safe()->all();
    }
}
