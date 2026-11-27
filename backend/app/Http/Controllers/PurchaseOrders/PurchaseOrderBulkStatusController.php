<?php

namespace App\Http\Controllers\PurchaseOrders;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PurchaseOrders\BulkPurchaseOrderStatusRequest;
use App\Services\PurchaseOrders\PurchaseOrderBulkStatusService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/purchase-orders/bulk-status (spec 0433): per-order outcome, an order
 * that cannot move never blocks the others.
 */
class PurchaseOrderBulkStatusController extends BaseApiController
{
    public function __construct(private readonly PurchaseOrderBulkStatusService $bulk) {}

    public function __invoke(BulkPurchaseOrderStatusRequest $request): JsonResponse
    {
        try {
            return $this->ok($this->bulk->handle(
                $request->user(),
                $request->ids(),
                $request->toStatus(),
                $request->dryRun(),
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
