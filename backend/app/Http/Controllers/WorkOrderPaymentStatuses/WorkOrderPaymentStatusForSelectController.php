<?php

namespace App\Http\Controllers\WorkOrderPaymentStatuses;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\WorkOrderPaymentStatuses\WorkOrderPaymentStatusForSelectRequest;
use App\Http\Resources\WorkOrderPaymentStatusForSelectResource;
use App\Services\WorkOrderPaymentStatusService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/work-order-payment-statuses/for-select — minimal, searchable, paginated
 * work order payment status list feeding entity-backed selects (spec 0201, ADR 0011 the
 * for-select standard).
 *
 * Thin invokable controller: validation
 * (WorkOrderPaymentStatusForSelectRequest), Service call, paginated response. No
 * permission gate beyond `auth:sanctum` (ADR 0011, amended 2026-07-31):
 * option lists feed forms whose actor may legitimately lack browse
 * rights on the source module.
 *
 * @see WorkOrderPaymentStatusService::forSelect
 */
class WorkOrderPaymentStatusForSelectController extends BaseApiController
{
    public function __construct(private readonly WorkOrderPaymentStatusService $service) {}

    public function __invoke(WorkOrderPaymentStatusForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                WorkOrderPaymentStatusForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
