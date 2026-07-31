<?php

namespace App\Http\Controllers\OpportunityStatuses;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\OpportunityStatuses\OpportunityStatusForSelectRequest;
use App\Http\Resources\OpportunityStatusForSelectResource;
use App\Services\OpportunityStatusService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/opportunity-statuses/for-select — minimal, searchable, paginated
 * opportunity status list feeding entity-backed selects (spec 0043, ADR 0011
 * the for-select standard).
 *
 * Thin invokable controller: validation
 * (OpportunityStatusForSelectRequest), Service call, paginated
 * response. No permission gate beyond `auth:sanctum` (ADR 0011, amended
 * 2026-07-31): option lists feed forms whose actor may legitimately
 * lack browse rights on the source module.
 *
 * @see OpportunityStatusService::forSelect
 */
class OpportunityStatusForSelectController extends BaseApiController
{
    public function __construct(private readonly OpportunityStatusService $service) {}

    public function __invoke(OpportunityStatusForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                OpportunityStatusForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
