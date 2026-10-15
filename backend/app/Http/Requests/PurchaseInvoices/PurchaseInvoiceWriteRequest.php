<?php

namespace App\Http\Requests\PurchaseInvoices;

use App\Enums\PurchaseInvoiceType;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Write payload of a payable document (spec 0250), shared by create (POST) and
 * edit (PUT, or PATCH since spec 0262: the keys not sent take their stored
 * value, lines and ODA links included). Type and supplier number are set on create and then change
 * only through the details request. Authorization stays in the controller via
 * the Policy; the rules that need the database state (parties, orders, bank,
 * total > 0) live in PurchaseInvoiceService.
 */
class PurchaseInvoiceWriteRequest extends FormRequest
{
    public const int NOTES_MAX_LENGTH = 5000;

    public const int MAX_LINES = 200;

    public const int MAX_ORDERS = 50;

    public const int NUMBER_MAX_LENGTH = 30;

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
            ...$this->creationRules(),
            'document_date' => ['required', 'date'],
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'supplier_id' => ['required', 'integer', Rule::exists('registries', 'id')->where('is_supplier', true)],
            // Active methods only; the one already on the document stays valid even if deactivated since.
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where(
                fn ($methods) => $methods->where('is_active', true)->orWhere('id', $this->storedDocument()?->payment_method_id),
            )],
            'financial_account_id' => ['nullable', 'integer', Rule::exists('financial_accounts', 'id')],
            'company_site_id' => ['nullable', 'integer', Rule::exists('company_sites', 'id')->where('company_id', $this->input('company_id'))],
            'operational_site_id' => ['nullable', 'integer', Rule::exists('operational_sites', 'id')],
            'cost_type_id' => ['nullable', 'integer', $this->costTypeRule()],
            'work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')],
            'notes' => ['nullable', 'string', 'max:'.self::NOTES_MAX_LENGTH],
            'withholding_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'enasarco_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'purchase_order_ids' => ['nullable', 'array', 'max:'.self::MAX_ORDERS],
            'purchase_order_ids.*' => ['integer', 'distinct', Rule::exists('purchase_orders', 'id')],
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.purchase_order_line_id' => ['nullable', 'integer'],
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lines.*.unit_price' => ['required', 'numeric', 'between:-999999999999,999999999999'],
            'lines.*.vat_rate_id' => ['nullable', 'integer', Rule::exists('vat_rates', 'id')],
            'lines.*.cost_type_id' => ['required', 'integer', $this->costTypeRule()],
            'lines.*.work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')],
        ];
    }

    /**
     * Spec 0262, D-1: a PATCH sends only what changed; the rest (lines and ODA
     * links included) is the stored document, so validation and the service
     * see the whole document exactly as the full edit would send it.
     */
    protected function prepareForValidation(): void
    {
        $document = $this->storedDocument();

        if ($document === null || ! $this->isMethod('PATCH')) {
            return;
        }

        $stored = [
            'document_date' => $document->document_date?->format('Y-m-d'),
            'company_id' => $document->company_id,
            'supplier_id' => $document->supplier_id,
            'payment_method_id' => $document->payment_method_id,
            'financial_account_id' => $document->financial_account_id,
            'company_site_id' => $document->company_site_id,
            'operational_site_id' => $document->operational_site_id,
            'cost_type_id' => $document->cost_type_id,
            'work_order_id' => $document->work_order_id,
            'notes' => $document->notes,
            'withholding_amount' => $document->withholding_amount,
            'enasarco_amount' => $document->enasarco_amount,
            'purchase_order_ids' => $document->purchaseOrders()->pluck('purchase_orders.id')->all(),
        ];

        if (! $this->has('lines')) {
            $stored['lines'] = $document->lines()->get()->map(static fn (PurchaseInvoiceLine $line): array => [
                'id' => $line->id,
                'purchase_order_line_id' => $line->purchase_order_line_id,
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'vat_rate_id' => $line->vat_rate_id,
                'cost_type_id' => $line->cost_type_id,
                'work_order_id' => $line->work_order_id,
            ])->all();
        }

        $this->merge(array_diff_key($stored, $this->all()));
    }

    private function storedDocument(): ?PurchaseInvoice
    {
        $document = $this->route('purchaseInvoice');

        return $document instanceof PurchaseInvoice ? $document : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->safe()->all();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function creationRules(): array
    {
        if (! $this->isMethod('POST')) {
            return [];
        }

        return [
            'document_type' => ['required', Rule::in(PurchaseInvoiceType::values())],
            'document_number' => ['required_if:document_type,'.PurchaseInvoiceType::Invoice->value, 'nullable', 'string', 'max:'.self::NUMBER_MAX_LENGTH],
        ];
    }

    /**
     * Active cost types only; the one a stored line already uses stays valid
     * even if it was deactivated since.
     */
    private function costTypeRule(): mixed
    {
        $document = $this->route('purchaseInvoice');
        $kept = $document instanceof PurchaseInvoice ? $document->lines()->pluck('cost_type_id')->all() : [];

        return Rule::exists('cost_types', 'id')->where(
            fn ($costTypes) => $costTypes->where('is_active', true)->orWhereIn('id', $kept),
        );
    }
}
