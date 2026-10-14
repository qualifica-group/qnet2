<?php

namespace App\Http\Requests\PurchaseOrders;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/purchase-orders/{purchaseOrder}/lines/{line}/close-residual
 * (spec 0254, D-3). Authorization is the `receive` ability in the controller.
 */
class ClosePurchaseOrderLineResidualRequest extends FormRequest
{
    private const int MAX_REASON_LENGTH = 500;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:1', 'max:'.self::MAX_REASON_LENGTH]];
    }

    public function reason(): string
    {
        return (string) $this->safe()->input('reason');
    }
}
