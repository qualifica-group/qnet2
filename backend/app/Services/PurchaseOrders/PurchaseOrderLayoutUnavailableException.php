<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use RuntimeException;

/**
 * Thrown when no document layout can render a purchase order (spec 0210): no
 * explicit layout and no active default for the `purchase_orders` module.
 * Callers map it to a 422.
 */
final class PurchaseOrderLayoutUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('document_layouts.purchase_order_no_layout_available'));
    }
}
