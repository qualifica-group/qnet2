<?php

namespace App\Http\Controllers\Quotes;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Quotes\QuoteForSelectRequest;
use App\Http\Resources\QuoteForSelectResource;
use App\Services\Quotes\QuoteForSelectService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/quotes/for-select — minimal, searchable, paginated offer list
 * feeding entity-backed selects (ADR 0011). Feeds the `rewarded-referents`
 * "Offerta" advanced filter (spec 0059 amendment A-01).
 *
 * Thin invokable controller: validation, Service call, paginated response. No
 * permission gate beyond `auth:sanctum` (ADR 0011, amended 2026-07-31, same
 * as opportunities/for-select): option lists feed forms and filters whose
 * actor may legitimately lack browse rights on the source module, and the
 * projection carries nothing but the code and the title.
 *
 * @see QuoteForSelectService::forSelect
 */
class QuoteForSelectController extends BaseApiController
{
    public function __construct(private readonly QuoteForSelectService $service) {}

    public function __invoke(QuoteForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                QuoteForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
