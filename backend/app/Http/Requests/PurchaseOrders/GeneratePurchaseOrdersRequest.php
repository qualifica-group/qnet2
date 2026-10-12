<?php

namespace App\Http\Requests\PurchaseOrders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload of POST /api/purchase-request-lines/purchase-orders (spec 0210,
 * D-17). Who may generate, and which lines qualify, is decided by
 * PurchaseOrderFromRequestLinesService.
 */
class GeneratePurchaseOrdersRequest extends FormRequest
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
            'line_ids.*' => ['required', 'integer', 'distinct', Rule::exists('purchase_request_lines', 'id')],
            'cost_type_id' => ['required', 'integer', Rule::exists('cost_types', 'id')->where('is_active', true)],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')],
            'order_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function lineIds(): array
    {
        return array_map('intval', $this->validated('line_ids'));
    }

    public function costTypeId(): int
    {
        return (int) $this->validated('cost_type_id');
    }

    public function paymentMethodId(): ?int
    {
        $id = $this->validated('payment_method_id');

        return $id === null ? null : (int) $id;
    }

    public function orderDate(): string
    {
        return $this->validated('order_date') ?? now()->toDateString();
    }
}
