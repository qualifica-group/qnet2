<?php

namespace App\Http\Controllers\PurchaseOrders;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Controllers\PurchaseOrders\Concerns\RespondsWithPurchaseOrderDetail;
use App\Http\Requests\PurchaseOrders\ClosePurchaseOrderLineResidualRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\PurchaseOrders\PurchaseOrderLineResidualService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Close and reopen the residual of an order line (spec 0254, D-3), behind
 * `purchase-orders.receive`. The line is resolved through the order
 * (scopeBindings): a line of another order is a 404.
 */
class PurchaseOrderLineResidualController extends BaseApiController
{
    use AuthorizesRequests, RespondsWithPurchaseOrderDetail;

    public function __construct(private readonly PurchaseOrderLineResidualService $service) {}

    /**
     * POST /api/purchase-orders/{purchaseOrder}/lines/{line}/close-residual (409 state, 422 reason)
     */
    public function store(ClosePurchaseOrderLineResidualRequest $request, PurchaseOrder $purchaseOrder, PurchaseOrderLine $line): JsonResponse
    {
        try {
            $this->authorize('receive', $purchaseOrder);

            $this->service->close($purchaseOrder, $line, $request->reason(), $request->user());
            [$order, $permissions] = $this->orderDetail($request->user(), $purchaseOrder);

            return $this->okWithPermissions($order, $permissions);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseOrder' => $purchaseOrder->id, 'line' => $line->id]);
        }
    }

    /**
     * DELETE /api/purchase-orders/{purchaseOrder}/lines/{line}/close-residual (409 not closed or cancelled)
     */
    public function destroy(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderLine $line): JsonResponse
    {
        try {
            $this->authorize('receive', $purchaseOrder);

            $this->service->reopen($purchaseOrder, $line, $request->user());
            [$order, $permissions] = $this->orderDetail($request->user(), $purchaseOrder);

            return $this->okWithPermissions($order, $permissions);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseOrder' => $purchaseOrder->id, 'line' => $line->id]);
        }
    }
}
