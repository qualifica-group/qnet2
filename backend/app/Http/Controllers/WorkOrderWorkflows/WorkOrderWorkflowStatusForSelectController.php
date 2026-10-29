<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkOrderWorkflows;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\WorkOrderWorkflows\WorkOrderWorkflowStatusForSelectRequest;
use App\Http\Resources\WorkOrderWorkflowStatusForSelectResource;
use App\Services\WorkOrderWorkflows\WorkOrderWorkflowStatusForSelectService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/work-order-workflow-statuses/for-select — every status of every
 * workflow (and the default set) for the task template row picker (spec 0323,
 * D-5; ADR 0011). No permission gate beyond `auth:sanctum`.
 */
class WorkOrderWorkflowStatusForSelectController extends BaseApiController
{
    public function __construct(private readonly WorkOrderWorkflowStatusForSelectService $service) {}

    public function __invoke(WorkOrderWorkflowStatusForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                WorkOrderWorkflowStatusForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
