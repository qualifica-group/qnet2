<?php

namespace App\Http\Requests\PurchaseOrders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload of POST /api/purchase-orders/{purchaseOrder}/merge (spec 0210,
 * D-19): the lines to move into the order. Same supplier / company / state
 * rules are checked by PurchaseOrderMergeService against the locked rows.
 */
class PurchaseOrderMergeRequest extends FormRequest
{
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
            'line_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'line_ids.*' => ['required', 'integer', 'distinct', Rule::exists('purchase_order_lines', 'id')],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function lineIds(): array
    {
        return array_map('intval', $this->validated('line_ids'));
    }
}
