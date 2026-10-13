<?php

declare(strict_types=1);

namespace App\Http\Controllers\PurchaseRequestPriorities;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PurchaseRequestPriorities\StorePurchaseRequestPriorityRequest;
use App\Http\Requests\PurchaseRequestPriorities\UpdatePurchaseRequestPriorityRequest;
use App\Http\Requests\Statuses\ReorderStatusesRequest;
use App\Http\Resources\PurchaseRequestPriorityResource;
use App\Models\PurchaseRequestPriority;
use App\Models\User;
use App\Services\PurchaseRequestPriorityService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `purchase-request-priorities` resource (spec 0101), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (PurchaseRequestPriorityPolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see PurchaseRequestPriorityService
 */
class PurchaseRequestPriorityController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PurchaseRequestPriorityService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/purchase-request-priorities/{purchaseRequestPriority} — single purchase request priority (view row-action).
     */
    public function show(Request $request, PurchaseRequestPriority $purchaseRequestPriority): JsonResponse
    {
        try {
            $this->authorize('view', $purchaseRequestPriority);

            return $this->okWithPermissions(
                new PurchaseRequestPriorityResource($purchaseRequestPriority),
                $this->buildPermissions($request->user(), $purchaseRequestPriority),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseRequestPriority' => $purchaseRequestPriority->id]);
        }
    }

    /**
     * POST /api/purchase-request-priorities — create a new purchase request priority.
     */
    public function store(StorePurchaseRequestPriorityRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', PurchaseRequestPriority::class);

            $purchaseRequestPriority = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new PurchaseRequestPriorityResource($purchaseRequestPriority),
                $this->buildPermissions($request->user(), $purchaseRequestPriority),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/purchase-request-priorities/{purchaseRequestPriority} — update an existing purchase request priority.
     */
    public function update(UpdatePurchaseRequestPriorityRequest $request, PurchaseRequestPriority $purchaseRequestPriority): JsonResponse
    {
        try {
            $this->authorize('update', $purchaseRequestPriority);

            $purchaseRequestPriority = $this->service->update($purchaseRequestPriority, $request->toData());

            return $this->okWithPermissions(
                new PurchaseRequestPriorityResource($purchaseRequestPriority),
                $this->buildPermissions($request->user(), $purchaseRequestPriority),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseRequestPriority' => $purchaseRequestPriority->id]);
        }
    }

    /**
     * DELETE /api/purchase-request-priorities/{purchaseRequestPriority} — delete a purchase request priority (409 when referenced by a purchase request,
     * spec 0101 D-8).
     */
    public function destroy(PurchaseRequestPriority $purchaseRequestPriority): JsonResponse
    {
        try {
            $this->authorize('delete', $purchaseRequestPriority);

            $this->service->delete($purchaseRequestPriority);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseRequestPriority' => $purchaseRequestPriority->id]);
        }
    }

    /**
     * POST /api/purchase-request-priorities/reorder — resequence the rows (AC-049). Gated on
     * `purchase-request-priorities.update` directly: no single Model instance exists for a bulk
     * reorder, so there is no Policy `update($user, $model)` to delegate to
     * — mirrors TaskStatusController::reorder.
     */
    public function reorder(ReorderStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize('purchase-request-priorities.update');

            $reordered = $this->service->reorder($request->orderedIds());

            return $this->ok($reordered->map(static fn (PurchaseRequestPriority $purchaseRequestPriority): array => [
                'id' => $purchaseRequestPriority->id,
                'sort_order' => $purchaseRequestPriority->sort_order,
            ])->all());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?PurchaseRequestPriority $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('purchase-request-priorities'), $actor, $model);
    }
}
