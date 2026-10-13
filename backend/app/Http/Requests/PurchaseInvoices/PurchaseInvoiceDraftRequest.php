<?php

namespace App\Http\Requests\PurchaseInvoices;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /purchase-invoices/draft (spec 0250, D-1): the orders to register an
 * invoice from. Supplier/company/status consistency is checked by the builder.
 */
class PurchaseInvoiceDraftRequest extends FormRequest
{
    public const int MAX_ORDERS = 50;

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
            'purchase_order_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_ORDERS],
            'purchase_order_ids.*' => ['integer', 'distinct', Rule::exists(PurchaseOrder::class, 'id')],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function purchaseOrderIds(): array
    {
        return array_map('intval', $this->validated('purchase_order_ids'));
    }
}
