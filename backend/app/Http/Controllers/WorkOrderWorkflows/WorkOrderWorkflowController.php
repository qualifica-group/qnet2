<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkOrderWorkflows;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\WorkOrderWorkflows\StoreWorkOrderWorkflowRequest;
use App\Http\Requests\WorkOrderWorkflows\UpdateDefaultStatusesRequest;
use App\Http\Requests\WorkOrderWorkflows\UpdateWorkOrderWorkflowRequest;
use App\Http\Resources\WorkOrderWorkflowResource;
use App\Http\Resources\WorkOrderWorkflowStatusResource;
use App\Models\User;
use App\Models\WorkOrderWorkflow;
use App\Services\WorkOrderWorkflowService;
use App\Support\WorkOrderWorkflows\WorkOrderCriterionFieldRegistry;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * CRUD + default-status-set endpoints for the `work-order-workflows` configurator
 * (spec 0322, twin of quote-workflows): view/create/update/
 * delete of a workflow (criteria + statuses in the same request payload),
 * plus the allow-listed criterion fields and the GLOBAL default status set.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (WorkOrderWorkflowPolicy), Service call, response. No business logic.
 *
 * @see WorkOrderWorkflowService
 */
class WorkOrderWorkflowController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly WorkOrderWorkflowService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
        private readonly WorkOrderCriterionFieldRegistry $criterionFieldRegistry,
    ) {}

    /**
     * GET /api/work-order-workflows/{workOrderWorkflow}.
     */
    public function show(WorkOrderWorkflow $workOrderWorkflow): JsonResponse
    {
        try {
            $this->authorize('view', $workOrderWorkflow);

            $workOrderWorkflow = $this->service->loadDetail($workOrderWorkflow);

            return $this->okWithPermissions(
                new WorkOrderWorkflowResource($workOrderWorkflow),
                $this->buildPermissions($workOrderWorkflow),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrderWorkflow' => $workOrderWorkflow->id]);
        }
    }

    /**
     * POST /api/work-order-workflows — create a workflow with its criteria and
     * (optional custom) statuses in one request.
     */
    public function store(StoreWorkOrderWorkflowRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', WorkOrderWorkflow::class);

            $workOrderWorkflow = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new WorkOrderWorkflowResource($workOrderWorkflow),
                $this->buildPermissions($workOrderWorkflow),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/work-order-workflows/{workOrderWorkflow}.
     */
    public function update(UpdateWorkOrderWorkflowRequest $request, WorkOrderWorkflow $workOrderWorkflow): JsonResponse
    {
        try {
            $this->authorize('update', $workOrderWorkflow);

            $workOrderWorkflow = $this->service->update($workOrderWorkflow, $request->toData());

            return $this->okWithPermissions(
                new WorkOrderWorkflowResource($workOrderWorkflow),
                $this->buildPermissions($workOrderWorkflow),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrderWorkflow' => $workOrderWorkflow->id]);
        }
    }

    /**
     * DELETE /api/work-order-workflows/{workOrderWorkflow} — deletes the workflow and
     * re-resolves every impacted work order.
     */
    public function destroy(WorkOrderWorkflow $workOrderWorkflow): JsonResponse
    {
        try {
            $this->authorize('delete', $workOrderWorkflow);

            $this->service->delete($workOrderWorkflow);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrderWorkflow' => $workOrderWorkflow->id]);
        }
    }

    /**
     * GET /api/work-order-workflows/criterion-fields — the allow-listed criterion
     * fields, for the criteria editor's field select.
     */
    public function criterionFields(): JsonResponse
    {
        try {
            $this->authorize('work-order-workflows.view');

            return $this->ok($this->criterionFieldRegistry->allowedFields());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/work-order-workflows/default-statuses — the GLOBAL default status
     * set, ordered.
     */
    public function defaultStatuses(): JsonResponse
    {
        try {
            $this->authorize('work-order-workflows.view');

            return $this->ok(WorkOrderWorkflowStatusResource::collection($this->service->defaultStatuses()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT /api/work-order-workflows/default-statuses — syncs the GLOBAL default
     * status set's custom rows.
     */
    public function updateDefaultStatuses(UpdateDefaultStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize('work-order-workflows.update');

            $statuses = $this->service->syncDefaultStatuses($request->statuses());

            return $this->ok(WorkOrderWorkflowStatusResource::collection($statuses));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * The `permissions` block for $model (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(?WorkOrderWorkflow $model): array
    {
        /** @var User $actor */
        $actor = request()->user();

        return $this->permissionsBuilder->build($this->authorization->resolve('work-order-workflows'), $actor, $model);
    }
}
