<?php

declare(strict_types=1);

namespace App\Http\Controllers\PurchaseOrderStatuses;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PurchaseOrderStatuses\UpdatePurchaseOrderStatusRequest;
use App\Http\Requests\Statuses\ReorderStatusesRequest;
use App\Http\Resources\PurchaseOrderStatusLabelResource;
use App\Models\PurchaseOrderStatusLabel;
use App\Models\User;
use App\Services\PurchaseOrderStatusLabelService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Endpoints of the `purchase-order-statuses` presentation catalogue (spec 0248):
 * show, update and reorder. No create/delete: the rows are the enum cases.
 * Thin controller: FormRequest, Policy, Service, Resource.
 */
class PurchaseOrderStatusController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PurchaseOrderStatusLabelService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/purchase-order-statuses/{purchaseOrderStatus}
     */
    public function show(Request $request, PurchaseOrderStatusLabel $purchaseOrderStatus): JsonResponse
    {
        try {
            $this->authorize('view', $purchaseOrderStatus);

            return $this->okWithPermissions(
                new PurchaseOrderStatusLabelResource($purchaseOrderStatus),
                $this->buildPermissions($request->user(), $purchaseOrderStatus),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseOrderStatus' => $purchaseOrderStatus->id]);
        }
    }

    /**
     * PUT/PATCH /api/purchase-order-statuses/{purchaseOrderStatus}
     */
    public function update(UpdatePurchaseOrderStatusRequest $request, PurchaseOrderStatusLabel $purchaseOrderStatus): JsonResponse
    {
        try {
            $this->authorize('update', $purchaseOrderStatus);

            $label = $this->service->update($purchaseOrderStatus, $request->updatableAttributes());

            return $this->okWithPermissions(
                new PurchaseOrderStatusLabelResource($label),
                $this->buildPermissions($request->user(), $label),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseOrderStatus' => $purchaseOrderStatus->id]);
        }
    }

    /**
     * POST /api/purchase-order-statuses/reorder: gated on
     * `purchase-order-statuses.update` (no single model for a bulk reorder).
     */
    public function reorder(ReorderStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize('purchase-order-statuses.update');

            $reordered = $this->service->reorder($request->orderedIds());

            return $this->ok($reordered->map(static fn (PurchaseOrderStatusLabel $label): array => [
                'id' => $label->id,
                'sort_order' => $label->sort_order,
            ])->all());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, PurchaseOrderStatusLabel $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('purchase-order-statuses'), $actor, $model);
    }
}
