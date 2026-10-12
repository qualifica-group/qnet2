<?php

namespace App\Http\Requests\PurchaseOrders;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/purchase-orders/{purchaseOrder}/status (spec 0210, D-9). The
 * matrix and the permissions are enforced by PurchaseOrderStatusService.
 */
class PurchaseOrderStatusRequest extends FormRequest
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
        return ['to_status' => ['required', 'string', Rule::enum(PurchaseOrderStatus::class)]];
    }

    public function toStatus(): PurchaseOrderStatus
    {
        return PurchaseOrderStatus::from((string) $this->validated('to_status'));
    }
}
