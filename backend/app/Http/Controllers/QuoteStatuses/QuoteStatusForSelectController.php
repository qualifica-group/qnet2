<?php

namespace App\Http\Controllers\QuoteStatuses;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\QuoteStatuses\QuoteStatusForSelectRequest;
use App\Http\Resources\QuoteStatusForSelectResource;
use App\Models\QuoteStatus;
use App\Services\QuoteStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/quote-statuses/for-select — minimal, searchable, paginated quote
 * status list feeding entity-backed selects (spec 0065, ADR 0011 the
 * for-select standard). A plain clone of
 * OpportunityStatusForSelectController.
 *
 * Thin invokable controller: validation (QuoteStatusForSelectRequest),
 * server-side authorization (quote-statuses.viewAny via QuoteStatusPolicy),
 * Service call, paginated response.
 *
 * @see QuoteStatusService::forSelect
 */
class QuoteStatusForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly QuoteStatusService $service) {}

    public function __invoke(QuoteStatusForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', QuoteStatus::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                QuoteStatusForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
