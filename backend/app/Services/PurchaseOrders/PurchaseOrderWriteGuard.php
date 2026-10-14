<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\DataObjects\PurchaseOrders\PurchaseOrderData;
use App\Models\PurchaseOrder;
use Illuminate\Validation\ValidationException;

/**
 * What an edit may change on an order, by status (spec 0210 D-10, spec 0259):
 * everything but the company while draft or in validation; past approval only
 * the service fields (PurchaseOrderData::SERVICE_FIELDS), never the lines;
 * nothing once cancelled. Shared by the write request (early 409) and
 * PurchaseOrderService::update (under the row lock).
 */
final class PurchaseOrderWriteGuard
{
    /**
     * @param  array<int, string>  $fields  the submitted field keys (`lines` included when sent)
     */
    public function allows(PurchaseOrder $order, array $fields): bool
    {
        if ($order->status->isEditable()) {
            return true;
        }

        return $order->status->allowsServiceEdits() && array_diff($fields, PurchaseOrderData::SERVICE_FIELDS) === [];
    }

    public function assertWritable(PurchaseOrder $order, PurchaseOrderData $data): void
    {
        $fields = [...array_keys($data->header), ...($data->sectorIds === null ? [] : ['sector_ids']), ...($data->lines === null ? [] : ['lines'])];

        if (! $this->allows($order, $fields)) {
            abort(409, __($order->status->allowsServiceEdits() ? 'purchase_orders.only_service_fields' : 'purchase_orders.not_editable'));
        }

        // The numbering is per company: moving an order to another one would break it (spec 0244, D-1).
        if (array_key_exists('company_id', $data->header) && (int) $data->header['company_id'] !== (int) $order->company_id) {
            throw ValidationException::withMessages(['company_id' => [__('purchase_orders.company_locked')]]);
        }
    }
}
