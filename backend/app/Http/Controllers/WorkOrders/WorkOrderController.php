<?php

namespace App\Http\Controllers\WorkOrders;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\FormMode;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\WorkOrders\StoreWorkOrderRequest;
use App\Http\Requests\WorkOrders\UpdateWorkOrderRequest;
use App\Http\Requests\WorkOrders\WorkOrderFormContextRequest;
use App\Http\Resources\WorkOrderFormContextResource;
use App\Http\Resources\WorkOrderResource;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use App\WorkOrders\WorkOrderAttributeResolver;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `work-orders` resource (spec 0093), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (WorkOrderPolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see WorkOrderService
 */
class WorkOrderController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly WorkOrderService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
        private readonly WorkOrderAttributeResolver $attributeResolver,
    ) {}

    /**
     * POST /api/work-orders/form-context (spec 0098, D-7): live preview of
     * the applicable "Informazioni aggiuntive" and their layout for the
     * `quote_line_ids` composed so far — serves BOTH the WorkOrder form and
     * the Contract's "Programma" dialog. Authorization is the resource-level
     * `create` OR `update` ability (frozen api-contract): neither maps to a
     * single model instance here, so it is checked directly rather than via
     * WorkOrderPolicy's own per-record `update`.
     */
    public function formContext(WorkOrderFormContextRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            abort_unless($user !== null && ($user->can('work-orders.create') || $user->can('work-orders.update')), 403);

            return $this->ok(new WorkOrderFormContextResource(
                $this->attributeResolver->formContext($request->quoteLineIds(), FormMode::Create),
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/work-orders/next-code — non-binding preview of the next
     * sequential `code` (D-1), for the create form's auto-fill.
     */
    public function nextCode(): JsonResponse
    {
        try {
            $this->authorize('create', WorkOrder::class);

            return $this->ok(['code' => $this->service->previewNextCode()]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/work-orders/{workOrder} — single work order (view row-action).
     */
    public function show(Request $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('view', $workOrder);

            $workOrder = $this->service->loadDetail($workOrder);

            return $this->okWithPermissions(
                new WorkOrderResource($workOrder),
                $this->buildPermissions($request->user(), $workOrder),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * POST /api/work-orders — create a new work order.
     */
    public function store(StoreWorkOrderRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', WorkOrder::class);

            $workOrder = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new WorkOrderResource($workOrder),
                $this->buildPermissions($request->user(), $workOrder),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/work-orders/{workOrder} — update an existing work order.
     */
    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('update', $workOrder);

            $workOrder = $this->service->update($workOrder, $request->toData());

            return $this->okWithPermissions(
                new WorkOrderResource($workOrder),
                $this->buildPermissions($request->user(), $workOrder),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * DELETE /api/work-orders/{workOrder} — delete a work order
     * (WorkOrderService::delete(), spec 0093 D-11).
     */
    public function destroy(WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('delete', $workOrder);

            $this->service->delete($workOrder);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?WorkOrder $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('work-orders'), $actor, $model);
    }
}
