<?php

namespace App\Http\Controllers\QuoteOfferLines;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\QuoteOfferLines\QuoteOfferLineForSelectRequest;
use App\Http\Resources\QuoteOfferLineForSelectResource;
use App\Services\QuoteOfferLines\QuoteOfferLineForSelectService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/quote-offer-lines/for-select — minimal, searchable, paginated
 * REVENUE-line list scoped to ONE quote, feeding the WorkOrder form's row
 * multi-select (spec 0093, D-8).
 *
 * Explicit OR gate — `work-orders.view` (an operator compiling a commessa)
 * OR `quotes.view` — DELIBERATELY narrower than the app's usual for-select
 * default (ADR 0011, amended 2026-07-31: no gate beyond `auth:sanctum`):
 * this projection exposes ONE quote's own product/pricing lines, not an
 * anonymous option list, so the frozen data_contract (spec 0093) requires an
 * explicit permission check here.
 *
 * @see QuoteOfferLineForSelectService::forSelect
 */
class QuoteOfferLineForSelectController extends BaseApiController
{
    public function __construct(private readonly QuoteOfferLineForSelectService $service) {}

    public function __invoke(QuoteOfferLineForSelectRequest $request): JsonResponse
    {
        try {
            $actor = $request->user();

            if (! $actor->can('work-orders.view') && ! $actor->can('quotes.view')) {
                abort(403);
            }

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                QuoteOfferLineForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
