<?php

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Leads\LeadForSelectRequest;
use App\Http\Resources\LeadForSelectResource;
use App\Models\Lead;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/leads/for-select — minimal, searchable, paginated lead list
 * feeding entity-backed selects (amendment rev.1 A-1, ADR 0011). Feeds the
 * Opportunity create form's "Lead" select (spec 0040).
 *
 * Thin invokable controller: validation (LeadForSelectRequest), Service
 * call, paginated response. No permission gate beyond `auth:sanctum`
 * (ADR 0011, amended 2026-07-31): option lists feed forms whose actor
 * may legitimately lack browse rights on the source module.
 *
 * @see LeadService::forSelect
 */
class LeadForSelectController extends BaseApiController
{
    public function __construct(private readonly LeadService $service) {}

    public function __invoke(LeadForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                LeadForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
