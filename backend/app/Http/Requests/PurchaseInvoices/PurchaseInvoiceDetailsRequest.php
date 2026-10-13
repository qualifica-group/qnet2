<?php

namespace App\Http\Requests\PurchaseInvoices;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /purchase-invoices/{purchaseInvoice}/details (spec 0250, D-2): the
 * supplier number (it decides the type) and the notes. Authorization stays in
 * the controller.
 */
class PurchaseInvoiceDetailsRequest extends FormRequest
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
            'document_number' => ['sometimes', 'nullable', 'string', 'max:'.PurchaseInvoiceWriteRequest::NUMBER_MAX_LENGTH],
            'notes' => ['sometimes', 'nullable', 'string', 'max:'.PurchaseInvoiceWriteRequest::NOTES_MAX_LENGTH],
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
