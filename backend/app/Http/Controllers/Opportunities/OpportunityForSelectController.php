<?php

namespace App\Http\Controllers\Opportunities;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Opportunities\OpportunityForSelectRequest;
use App\Http\Resources\OpportunityForSelectResource;
use App\Models\Opportunity;
use App\Services\OpportunityService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/opportunities/for-select — minimal, searchable, paginated
 * opportunity list feeding entity-backed selects (ADR 0011). Feeds the
 * `rewarded-referents` "opportunity" advanced filter (spec 0059).
 *
 * Thin invokable controller: validation (OpportunityForSelectRequest),
 * server-side authorization (opportunities.viewAny via OpportunityPolicy),
 * Service call, paginated response.
 *
 * @see OpportunityService::forSelect
 */
class OpportunityForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly OpportunityService $service) {}

    public function __invoke(OpportunityForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Opportunity::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                OpportunityForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
