<?php

namespace App\Http\Controllers\PurchaseOrders;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PurchaseOrders\PurchaseOrderStatusRequest;
use App\Http\Requests\PurchaseOrders\PurchaseOrderWriteRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\PurchaseOrders\PurchaseOrderService;
use App\Services\PurchaseOrders\PurchaseOrderStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Endpoints of the `purchase-orders` resource (spec 0210). Thin controller:
 * FormRequest validation, server-side authorization (PurchaseOrderPolicy),
 * Service call. The list is the generic `purchase-orders` table domain.
 *
 * @see PurchaseOrderService
 */
class PurchaseOrderController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PurchaseOrderService $service,
        private readonly PurchaseOrderStatusService $statusService,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/purchase-orders/{purchaseOrder}
     */
    public function show(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        try {
            $this->authorize('view', $purchaseOrder);

            return $this->detailResponse($request->user(), $this->service->detail($purchaseOrder));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseOrder' => $purchaseOrder->id]);
        }
    }

    /**
     * POST /api/purchase-orders (`meta.created_ids` lists every monthly copy)
     */
    public function store(PurchaseOrderWriteRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', PurchaseOrder::class);

            $created = $this->service->create($request->toData(), $request->user());
            $ids = array_map(fn (PurchaseOrder $order): int => $order->id, $created);

            return $this->detailResponse($request->user(), $this->service->detail($created[0]), 'Created', HttpStatusEnum::CREATED, ['created_ids' => $ids]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT /api/purchase-orders/{purchaseOrder} (409 once past draft/in validation)
     */
    public function update(PurchaseOrderWriteRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        try {
            $this->authorize('update', $purchaseOrder);

            $updated = $this->service->update($purchaseOrder, $request->toData(), $request->user());

            return $this->detailResponse($request->user(), $this->service->detail($updated));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseOrder' => $purchaseOrder->id]);
        }
    }

    /**
     * DELETE /api/purchase-orders/{purchaseOrder} (409 once past draft/in validation)
     */
    public function destroy(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        try {
            $this->authorize('delete', $purchaseOrder);

            $this->service->delete($purchaseOrder, $request->user());

            return $this->ok(null, 'Deleted');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseOrder' => $purchaseOrder->id]);
        }
    }

    /**
     * POST /api/purchase-orders/{purchaseOrder}/status (403 permission, 422 `to_status` matrix)
     */
    public function status(PurchaseOrderStatusRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        try {
            $this->authorize('view', $purchaseOrder);

            $changed = $this->statusService->change($purchaseOrder, $request->toStatus(), $request->user());

            return $this->detailResponse($request->user(), $this->service->detail($changed));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseOrder' => $purchaseOrder->id]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function detailResponse(User $actor, PurchaseOrder $order, string $message = 'OK', HttpStatusEnum $status = HttpStatusEnum::OK, ?array $meta = null): JsonResponse
    {
        $permissions = $this->permissionsBuilder->build($this->authorization->resolve('purchase-orders'), $actor, $order);
        $resource = new PurchaseOrderResource($order, $permissions);

        if ($meta === null) {
            return $this->okWithPermissions($resource, $permissions, $message, $status);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $resource,
            'permissions' => $permissions,
            'meta' => $meta,
        ], $status->value);
    }
}
