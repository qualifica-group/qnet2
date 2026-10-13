<?php

namespace App\Http\Requests\PurchaseInvoices;

use App\Enums\PurchaseInvoiceType;
use App\Models\PurchaseInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Write payload of a payable document (spec 0250), shared by create (POST) and
 * full edit (PUT). Type and supplier number are set on create and then change
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
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            'financial_account_id' => ['nullable', 'integer', Rule::exists('financial_accounts', 'id')],
            'company_site_id' => ['nullable', 'integer', Rule::exists('company_sites', 'id')->where('company_id', $this->input('company_id'))],
            'operational_site_id' => ['nullable', 'integer', Rule::exists('operational_sites', 'id')],
            'cost_type_id' => ['nullable', 'integer', $this->costTypeRule()],
            'work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')],
            'notes' => ['nullable', 'string', 'max:'.self::NOTES_MAX_LENGTH],
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
