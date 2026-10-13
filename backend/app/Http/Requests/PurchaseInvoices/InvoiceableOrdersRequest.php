<?php

namespace App\Http\Requests\PurchaseInvoices;

use App\Models\Company;
use App\Models\Registry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /purchase-invoices/invoiceable-orders (spec 0250, D-1): the orders of a
 * supplier and company a payable document can be registered from.
 */
class InvoiceableOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('purchase-invoices.create') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Rule::exists(Registry::class, 'id')],
            'company_id' => ['required', 'integer', Rule::exists(Company::class, 'id')],
        ];
    }
}
