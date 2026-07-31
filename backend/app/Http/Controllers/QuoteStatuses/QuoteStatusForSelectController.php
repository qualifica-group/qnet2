<?php

namespace App\Http\Controllers\QuoteStatuses;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\QuoteStatuses\QuoteStatusForSelectRequest;
use App\Http\Resources\QuoteStatusForSelectResource;
use App\Services\QuoteStatusService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/quote-statuses/for-select — minimal, searchable, paginated quote
 * status list feeding entity-backed selects (spec 0065, ADR 0011 the
 * for-select standard). A plain clone of
 * OpportunityStatusForSelectController.
 *
 * Thin invokable controller: validation (QuoteStatusForSelectRequest),
 * Service call, paginated response. No permission gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31): option lists feed
 * forms whose actor may legitimately lack browse rights on the source
 * module.
 *
 * @see QuoteStatusService::forSelect
 */
class QuoteStatusForSelectController extends BaseApiController
{
    public function __construct(private readonly QuoteStatusService $service) {}

    public function __invoke(QuoteStatusForSelectRequest $request): JsonResponse
    {
        try {
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
