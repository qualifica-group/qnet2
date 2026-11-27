<?php

namespace App\Http\Requests\PurchaseOrders;

use App\Enums\PurchaseOrderStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/purchase-orders/bulk-status (spec 0433). The permission of the
 * target is decided order by order by PurchaseOrderStatusService; here only
 * the endpoint-level gate (view plus changeStatus or approve). Duplicate ids are tolerated and counted once.
 */
class BulkPurchaseOrderStatusRequest extends FormRequest
{
    /** States settable by hand (fulfilment is derived from the receipts). */
    public const array MANUAL_STATUSES = [
        PurchaseOrderStatus::Draft,
        PurchaseOrderStatus::InValidation,
        PurchaseOrderStatus::Approved,
        PurchaseOrderStatus::SentToSupplier,
        PurchaseOrderStatus::Cancelled,
    ];

    public function authorize(): bool
    {
        /** @var User $actor */
        $actor = $this->user();

        // Same gate as the single change, which also authorizes `view` on the order.
        return $actor->can('purchase-orders.view')
            && ($actor->can('purchase-orders.changeStatus') || $actor->can('purchase-orders.approve'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.config('purchase-orders.bulk_status_max')],
            'ids.*' => ['integer', Rule::exists('purchase_orders', 'id')],
            'to_status' => ['required', 'string', Rule::enum(PurchaseOrderStatus::class)->only(self::MANUAL_STATUSES)],
            'dry_run' => ['required', 'boolean'],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_unique(array_map('intval', $this->validated('ids'))));
    }

    public function toStatus(): PurchaseOrderStatus
    {
        return PurchaseOrderStatus::from((string) $this->validated('to_status'));
    }

    public function dryRun(): bool
    {
        return $this->boolean('dry_run');
    }
}
