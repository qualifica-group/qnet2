<?php

namespace App\Http\Controllers\Companies;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Companies\CompanyForSelectRequest;
use App\Http\Resources\CompanyForSelectResource;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/companies/for-select — minimal, searchable, paginated company list
 * feeding the user-form "company" select (spec 0015, ADR 0011 the for-select
 * standard), mirroring UserForSelectController.
 *
 * Thin invokable controller: validation (CompanyForSelectRequest),
 * Service call, paginated response. The query/search/hydration logic
 * lives in CompanyService::forSelect, not here. No permission gate
 * beyond `auth:sanctum` (ADR 0011, amended 2026-07-31): option lists
 * feed forms whose actor may legitimately lack browse rights on the
 * source module.
 *
 * @see CompanyService::forSelect
 */
class CompanyForSelectController extends BaseApiController
{
    public function __construct(private readonly CompanyService $service) {}

    public function __invoke(CompanyForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                CompanyForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
