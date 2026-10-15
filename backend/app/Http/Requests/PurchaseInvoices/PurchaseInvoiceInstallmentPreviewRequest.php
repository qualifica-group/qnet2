<?php

namespace App\Http\Requests\PurchaseInvoices;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /purchase-invoices/installment-preview (spec 0250, D-4). Authorization
 * (create or update ability) stays in the controller.
 */
class PurchaseInvoiceInstallmentPreviewRequest extends FormRequest
{
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
            'purchase_invoice_id' => ['nullable', 'integer', Rule::exists('purchase_invoices', 'id')],
            'document_date' => ['required', 'date'],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')],
            'net_amount' => ['required', 'numeric', 'between:-9999999999999,9999999999999'],
            'vat_amount' => ['required', 'numeric', 'between:-9999999999999,9999999999999'],
            'total_amount' => ['required', 'numeric', 'between:-9999999999999,9999999999999'],
            'withholding_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'enasarco_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
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
