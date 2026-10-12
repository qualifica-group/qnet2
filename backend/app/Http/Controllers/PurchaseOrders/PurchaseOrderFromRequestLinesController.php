<?php

namespace App\Http\Controllers\PurchaseOrders;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PurchaseOrders\GeneratePurchaseOrdersRequest;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrders\PurchaseOrderFromRequestLinesService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Generation of purchase orders from approved purchase request lines (spec
 * 0210, D-17). Needs `purchase-orders.create`; the capabilities on the request
 * lines are resolved by the service, which refuses the whole batch with a 403
 * when any line is out of reach.
 *
 * @see PurchaseOrderFromRequestLinesService
 */
class PurchaseOrderFromRequestLinesController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly PurchaseOrderFromRequestLinesService $service) {}

    /**
     * POST /api/purchase-request-lines/purchase-orders
     */
    public function store(GeneratePurchaseOrdersRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', PurchaseOrder::class);

            $result = $this->service->handle(
                $request->user(),
                $request->lineIds(),
                $request->costTypeId(),
                $request->paymentMethodId(),
                $request->orderDate(),
            );

            return $this->created([
                'purchase_orders' => array_map(fn (PurchaseOrder $order): array => [
                    'id' => $order->id,
                    'code' => $order->code(),
                    'supplier' => ['id' => $order->supplier->id, 'name' => $order->supplier->name],
                    'lines_count' => (int) $order->lines_count,
                ], $result['purchase_orders']),
                'closed_purchase_request_ids' => $result['closed_purchase_request_ids'],
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
