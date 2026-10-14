<?php

namespace App\Http\Requests\PurchaseOrders;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/purchase-orders/{purchaseOrder}/receipts (spec 0254, D-1). Shape only:
 * the order state and the residual of every line are checked by the service
 * under the locks. Authorization is the `receive` ability in the controller.
 */
class StorePurchaseOrderReceiptRequest extends FormRequest
{
    private const int MAX_LINES = 200;

    /** purchase_order_lines.quantity is decimal(12,3). */
    private const string MAX_QUANTITY = '999999999.999';

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
            'receipt_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'delivery_note_number' => ['nullable', 'string', 'max:50'],
            'delivery_note_date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.purchase_order_line_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:'.self::MAX_QUANTITY],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function receiptHeader(): array
    {
        return $this->safe()->only(['receipt_date', 'delivery_note_number', 'delivery_note_date', 'notes']);
    }

    /**
     * @return array<int, array{purchase_order_line_id: int, quantity: string}>
     */
    public function receiptLines(): array
    {
        return array_map(
            static fn (array $line): array => [
                'purchase_order_line_id' => (int) $line['purchase_order_line_id'],
                'quantity' => bcadd((string) $line['quantity'], '0', 3),
            ],
            array_values($this->safe()->input('lines')),
        );
    }
}
