<?php

namespace App\Http\Controllers\WorkOrderPaymentStatuses;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Statuses\ReorderStatusesRequest;
use App\Http\Requests\WorkOrderPaymentStatuses\StoreWorkOrderPaymentStatusRequest;
use App\Http\Requests\WorkOrderPaymentStatuses\UpdateWorkOrderPaymentStatusRequest;
use App\Http\Resources\WorkOrderPaymentStatusResource;
use App\Models\User;
use App\Models\WorkOrderPaymentStatus;
use App\Services\WorkOrderPaymentStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `work-order-payment-statuses` resource (spec 0201), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (WorkOrderPaymentStatusPolicy), Service call, response. No business logic, no
 * queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see WorkOrderPaymentStatusService
 */
class WorkOrderPaymentStatusController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly WorkOrderPaymentStatusService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/work-order-payment-statuses/{workOrderPaymentStatus} — single work order payment status (view
     * row-action).
     */
    public function show(Request $request, WorkOrderPaymentStatus $workOrderPaymentStatus): JsonResponse
    {
        try {
            $this->authorize('view', $workOrderPaymentStatus);

            return $this->okWithPermissions(
                new WorkOrderPaymentStatusResource($workOrderPaymentStatus),
                $this->buildPermissions($request->user(), $workOrderPaymentStatus),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrderPaymentStatus' => $workOrderPaymentStatus->id]);
        }
    }

    /**
     * POST /api/work-order-payment-statuses — create a new work order payment status.
     */
    public function store(StoreWorkOrderPaymentStatusRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', WorkOrderPaymentStatus::class);

            $workOrderPaymentStatus = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new WorkOrderPaymentStatusResource($workOrderPaymentStatus),
                $this->buildPermissions($request->user(), $workOrderPaymentStatus),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/work-order-payment-statuses/{workOrderPaymentStatus} — update an existing
     * work order payment status.
     */
    public function update(UpdateWorkOrderPaymentStatusRequest $request, WorkOrderPaymentStatus $workOrderPaymentStatus): JsonResponse
    {
        try {
            $this->authorize('update', $workOrderPaymentStatus);

            $workOrderPaymentStatus = $this->service->update($workOrderPaymentStatus, $request->toData());

            return $this->okWithPermissions(
                new WorkOrderPaymentStatusResource($workOrderPaymentStatus),
                $this->buildPermissions($request->user(), $workOrderPaymentStatus),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrderPaymentStatus' => $workOrderPaymentStatus->id]);
        }
    }

    /**
     * DELETE /api/work-order-payment-statuses/{workOrderPaymentStatus} — delete a work order payment status
     * (409 when a commessa line still uses it).
     */
    public function destroy(WorkOrderPaymentStatus $workOrderPaymentStatus): JsonResponse
    {
        try {
            $this->authorize('delete', $workOrderPaymentStatus);

            $this->service->delete($workOrderPaymentStatus);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrderPaymentStatus' => $workOrderPaymentStatus->id]);
        }
    }

    /**
     * POST /api/work-order-payment-statuses/reorder — resequence the rows (spec 0201). Gated
     * on `work-order-payment-statuses.update` directly (no single Model instance exists
     * for a bulk reorder, so there is no Policy `update($user, $model)` to
     * delegate to — mirrors RewardStatusController::reorder()).
     */
    public function reorder(ReorderStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize('work-order-payment-statuses.update');

            $reordered = $this->service->reorder($request->orderedIds());

            return $this->ok($reordered->map(static fn (WorkOrderPaymentStatus $workOrderPaymentStatus): array => [
                'id' => $workOrderPaymentStatus->id,
                'sort_order' => $workOrderPaymentStatus->sort_order,
                // spec 0201: the table has no `system_key` column, but
                // the shared `status-reorder` frontend feature's contract
                // requires the key to always be present.
                'system_key' => null,
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
    private function buildPermissions(User $actor, ?WorkOrderPaymentStatus $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('work-order-payment-statuses'), $actor, $model);
    }
}
