<?php

namespace App\Http\Controllers\PurchaseOrders;

use App\Enums\HttpStatusEnum;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Controllers\PurchaseOrders\Concerns\RespondsWithPurchaseOrderDetail;
use App\Http\Requests\PurchaseOrders\StorePurchaseOrderReceiptRequest;
use App\Http\Resources\PurchaseOrderReceiptResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceipt;
use App\Services\PurchaseOrders\PurchaseOrderReceiptService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Deliveries of a purchase order (spec 0254). Reading follows
 * `purchase-orders.view`; recording and cancelling need `purchase-orders.receive`.
 */
class PurchaseOrderReceiptController extends BaseApiController
{
    use AuthorizesRequests, RespondsWithPurchaseOrderDetail;

    public function __construct(private readonly PurchaseOrderReceiptService $service) {}

    /**
     * GET /api/purchase-orders/{purchaseOrder}/receipts (receipt_date desc, id desc)
     */
    public function index(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        try {
            $this->authorize('view', $purchaseOrder);

            $canDelete = $this->canDelete($request, $purchaseOrder);
            $receipts = $purchaseOrder->receipts()->with(PurchaseOrderReceiptResource::RELATIONS)->get();

            return $this->ok($receipts->map(fn (PurchaseOrderReceipt $receipt): PurchaseOrderReceiptResource => new PurchaseOrderReceiptResource($receipt, $canDelete))->all());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseOrder' => $purchaseOrder->id]);
        }
    }

    /**
     * POST /api/purchase-orders/{purchaseOrder}/receipts (409 not receivable, 422 per line)
     */
    public function store(StorePurchaseOrderReceiptRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        try {
            $this->authorize('receive', $purchaseOrder);

            $receipt = $this->service->create($purchaseOrder, $request->receiptHeader(), $request->receiptLines(), $request->user());
            $receipt->load(PurchaseOrderReceiptResource::RELATIONS);
            [$order] = $this->orderDetail($request->user(), $purchaseOrder);

            return $this->ok([
                'receipt' => new PurchaseOrderReceiptResource($receipt, $this->canDelete($request, $order->resource)),
                'order' => $order,
            ], 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseOrder' => $purchaseOrder->id]);
        }
    }

    /**
     * DELETE /api/purchase-orders/{purchaseOrder}/receipts/{receipt} (409 cancelled or below the invoiced quantity)
     */
    public function destroy(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderReceipt $receipt): JsonResponse
    {
        try {
            $this->authorize('receive', $purchaseOrder);

            $this->service->delete($purchaseOrder, $receipt, $request->user());
            [$order, $permissions] = $this->orderDetail($request->user(), $purchaseOrder);

            return $this->okWithPermissions($order, $permissions, 'Deleted');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseOrder' => $purchaseOrder->id, 'receipt' => $receipt->id]);
        }
    }

    private function canDelete(Request $request, PurchaseOrder $order): bool
    {
        return $request->user()->can('receive', $order) && $order->status !== PurchaseOrderStatus::Cancelled;
    }
}
