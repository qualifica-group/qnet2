<?php

declare(strict_types=1);

namespace App\Http\Controllers\PurchaseRequestLineStatuses;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PurchaseRequestLineStatuses\UpdatePurchaseRequestLineStatusRequest;
use App\Http\Requests\Statuses\ReorderStatusesRequest;
use App\Http\Resources\PurchaseRequestLineStatusResource;
use App\Models\PurchaseRequestLineStatusLabel;
use App\Models\User;
use App\Services\PurchaseRequestLineStatusLabelService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * show/update/reorder of the `purchase-request-line-statuses` resource (spec
 * 0248). Thin controller: FormRequest, Policy, Service. No create/delete.
 */
class PurchaseRequestLineStatusLabelController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PurchaseRequestLineStatusLabelService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function show(Request $request, PurchaseRequestLineStatusLabel $purchaseRequestLineStatus): JsonResponse
    {
        try {
            $this->authorize('view', $purchaseRequestLineStatus);

            return $this->okWithPermissions(
                new PurchaseRequestLineStatusResource($purchaseRequestLineStatus),
                $this->buildPermissions($request->user(), $purchaseRequestLineStatus),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseRequestLineStatus' => $purchaseRequestLineStatus->id]);
        }
    }

    public function update(UpdatePurchaseRequestLineStatusRequest $request, PurchaseRequestLineStatusLabel $purchaseRequestLineStatus): JsonResponse
    {
        try {
            $this->authorize('update', $purchaseRequestLineStatus);

            $status = $this->service->update($purchaseRequestLineStatus, $request->toData());

            return $this->okWithPermissions(
                new PurchaseRequestLineStatusResource($status),
                $this->buildPermissions($request->user(), $status),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseRequestLineStatus' => $purchaseRequestLineStatus->id]);
        }
    }

    /**
     * POST /api/purchase-request-line-statuses/reorder: gated on the resource
     * `update` permission (no single model exists for a bulk reorder).
     */
    public function reorder(ReorderStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize('purchase-request-line-statuses.update');

            $reordered = $this->service->reorder($request->orderedIds());

            return $this->ok($reordered->map(static fn (PurchaseRequestLineStatusLabel $status): array => [
                'id' => $status->id,
                'sort_order' => $status->sort_order,
            ])->all());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, PurchaseRequestLineStatusLabel $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('purchase-request-line-statuses'), $actor, $model);
    }
}
