<?php

namespace App\Http\Controllers\WorkOrders;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\WorkOrders\WorkOrderForSelectRequest;
use App\Http\Resources\WorkOrderForSelectResource;
use App\Services\WorkOrderService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/work-orders/for-select — minimal, searchable, paginated Commessa
 * list feeding entity-backed selects (ADR 0011), mirroring
 * OpportunityForSelectController. First producer: the Task form's "Commessa"
 * picker (spec 0101, `work_order_id`), which the frozen data_contract
 * requires but spec 0101 never provided an endpoint for.
 *
 * Thin invokable controller: validation (WorkOrderForSelectRequest), Service
 * call, paginated response OUTSIDE the envelope. No permission gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31) — but the rows ARE narrowed
 * by WorkOrderVisibilityScope inside the Service (spec 0096, user directive
 * 2026-09-02): without `work-orders.viewAll` an actor sees here exactly the
 * commesse the Commesse table already shows them, and not one more.
 *
 * @see WorkOrderService::forSelect
 */
class WorkOrderForSelectController extends BaseApiController
{
    public function __construct(private readonly WorkOrderService $service) {}

    public function __invoke(WorkOrderForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                WorkOrderForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
