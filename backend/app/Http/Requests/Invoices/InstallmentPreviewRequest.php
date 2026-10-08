<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /invoices/installment-preview (spec 0194, D-12). Authorization (create
 * or update ability) stays in the controller.
 */
class InstallmentPreviewRequest extends FormRequest
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
            'invoice_id' => ['nullable', 'integer', Rule::exists('invoices', 'id')],
            'document_date' => ['required', 'date'],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')],
            'net_amount' => ['required', 'numeric', 'between:-9999999999999,9999999999999'],
            'vat_amount' => ['required', 'numeric', 'between:-9999999999999,9999999999999'],
            'total_amount' => ['required', 'numeric', 'between:-9999999999999,9999999999999'],
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
