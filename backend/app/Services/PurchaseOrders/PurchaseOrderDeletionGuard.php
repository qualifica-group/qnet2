<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Models\PurchaseOrder;

/**
 * Conditions under which an order may disappear (spec 0244, D-6): shared by the
 * deletion and by the merge, which deletes a source left without lines.
 */
final class PurchaseOrderDeletionGuard
{
    /** 409 when the order is locked by its status or already linked to a purchase invoice. */
    public function assertDeletable(PurchaseOrder $order): void
    {
        if (! $order->status->isEditable()) {
            abort(409, __('purchase_orders.not_editable'));
        }

        if ($order->purchaseInvoices()->exists()) {
            abort(409, __('purchase_orders.linked_to_purchase_invoice'));
        }
    }
}
