<?php

namespace App\Http\Controllers\CompanySites;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\CompanySites\CompanySiteForSelectRequest;
use App\Http\Resources\CompanySiteForSelectResource;
use App\Services\CompanySiteService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/company-sites/for-select — minimal, searchable, paginated
 * company-site list feeding the Opportunity form's "company_site" select
 * (spec 0040, ADR 0011 the for-select standard), mirroring
 * CompanyForSelectController.
 *
 * Thin invokable controller: validation (CompanySiteForSelectRequest),
 * Service call, paginated response. The query/search/hydration/scope
 * logic lives in CompanySiteService::forSelect, not here. No permission
 * gate beyond `auth:sanctum` (ADR 0011, amended 2026-07-31): option
 * lists feed forms whose actor may legitimately lack browse rights on
 * the source module.
 *
 * @see CompanySiteService::forSelect
 */
class CompanySiteForSelectController extends BaseApiController
{
    public function __construct(private readonly CompanySiteService $service) {}

    public function __invoke(CompanySiteForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData(), $request->companyId());

            return $this->paginatedResponse(
                CompanySiteForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
