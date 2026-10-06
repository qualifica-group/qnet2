<?php

namespace App\Http\Controllers\ProformaRequests;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ProformaRequests\ProformaRequestNoteRequest;
use App\Http\Resources\ProformaRequestResource;
use App\Models\ProformaRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\ProformaRequestService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Endpoints of the `proforma-requests` resource (spec 0193). Thin controller:
 * FormRequest validation, server-side authorization (ProformaRequestPolicy,
 * plus WorkOrderPolicy::view on the work-order-scoped endpoints), Service call.
 *
 * @see ProformaRequestService
 */
class ProformaRequestController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ProformaRequestService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/work-orders/{workOrder}/proforma-requests/summary
     */
    public function summary(WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorizeForWorkOrder($workOrder);

            return $this->ok($this->service->summary($workOrder));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * POST /api/work-orders/{workOrder}/proforma-requests (409 on a pending
     * request, 422 with no billable line)
     */
    public function store(ProformaRequestNoteRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorizeForWorkOrder($workOrder);

            $created = $this->service->createForWorkOrder($workOrder, $request->user(), $request->note());

            return $this->ok(ProformaRequestResource::collection($created), 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * GET /api/proforma-requests/{proformaRequest}
     */
    public function show(Request $request, ProformaRequest $proformaRequest): JsonResponse
    {
        try {
            $this->authorize('view', $proformaRequest);

            return $this->detailResponse($request->user(), $this->service->detail($proformaRequest));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['proformaRequest' => $proformaRequest->id]);
        }
    }

    /**
     * PATCH /api/proforma-requests/{proformaRequest} (the note only)
     */
    public function update(ProformaRequestNoteRequest $request, ProformaRequest $proformaRequest): JsonResponse
    {
        try {
            $this->authorize('update', $proformaRequest);

            return $this->detailResponse($request->user(), $this->service->updateNote($proformaRequest, $request->note()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['proformaRequest' => $proformaRequest->id]);
        }
    }

    /**
     * DELETE /api/proforma-requests/{proformaRequest}
     */
    public function destroy(ProformaRequest $proformaRequest): JsonResponse
    {
        try {
            $this->authorize('delete', $proformaRequest);

            $this->service->delete($proformaRequest);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['proformaRequest' => $proformaRequest->id]);
        }
    }

    /**
     * Both gates of the work-order-scoped endpoints: the actor may raise
     * requests AND may see this (membership-scoped) work order.
     */
    private function authorizeForWorkOrder(WorkOrder $workOrder): void
    {
        $this->authorize('create', ProformaRequest::class);
        $this->authorize('view', $workOrder);
    }

    private function detailResponse(User $actor, ProformaRequest $proformaRequest): JsonResponse
    {
        return $this->okWithPermissions(
            new ProformaRequestResource($proformaRequest),
            $this->permissionsBuilder->build($this->authorization->resolve('proforma-requests'), $actor, $proformaRequest),
        );
    }
}
