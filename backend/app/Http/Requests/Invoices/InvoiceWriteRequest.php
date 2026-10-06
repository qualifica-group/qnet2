<?php

namespace App\Http\Requests\Invoices;

use App\Enums\InvoiceTag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * InvoiceWritePayload (spec 0194), shared by issue (POST) and full edit (PUT).
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
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            'financial_account_id' => ['nullable', 'integer', Rule::exists('financial_accounts', 'id')],
            'notes' => ['nullable', 'string', 'max:'.self::NOTES_MAX_LENGTH],
            'internal_note' => ['nullable', 'string', 'max:'.self::NOTES_MAX_LENGTH],
            'tag' => ['nullable', Rule::enum(InvoiceTag::class)],
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.quote_line_id' => ['nullable', 'integer'],
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'lines.*.unit_price' => ['required', 'numeric', 'between:-9999999999,9999999999'],
            'lines.*.vat_rate_id' => ['required', 'integer', Rule::exists('vat_rates', 'id')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->safe()->all();
    }
}
