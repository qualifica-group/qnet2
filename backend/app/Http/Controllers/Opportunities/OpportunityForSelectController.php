<?php

namespace App\Http\Controllers\Opportunities;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Opportunities\OpportunityForSelectRequest;
use App\Http\Resources\OpportunityForSelectResource;
use App\Services\Opportunities\OpportunityForSelectService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/opportunities/for-select — minimal, searchable, paginated
 * opportunity list feeding entity-backed selects (ADR 0011). Feeds the
 * `rewarded-referents` "opportunity" advanced filter (spec 0059).
 *
 * Thin invokable controller: validation (OpportunityForSelectRequest),
 * Service call, paginated response. No `viewAny` gate beyond `auth:sanctum`
 * (ADR 0011, amended 2026-07-31): option lists feed forms whose actor may
 * legitimately lack browse rights on the source module. Rows are still
 * narrowed to the actor's visible opportunities (spec 0316,
 * OpportunityVisibilityScope).
 *
 * @see OpportunityForSelectService::forSelect
 */
class OpportunityForSelectController extends BaseApiController
{
    public function __construct(private readonly OpportunityForSelectService $service) {}

    public function __invoke(OpportunityForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                OpportunityForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
                hasMore: $result->hasMore,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
