<?php

declare(strict_types=1);

namespace App\Http\Controllers\PurchaseOrderEmails;

use App\Http\Controllers\OwnerEmails\OwnerEmailComposeContextController;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/purchase-orders/{purchaseOrder}/emails/compose-context (spec
 * 0210): the purchase order binding of OwnerEmailComposeContextController.
 */
class PurchaseOrderEmailComposeContextController extends OwnerEmailComposeContextController
{
    public function __invoke(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->composeContext($request, $purchaseOrder);
    }
}
