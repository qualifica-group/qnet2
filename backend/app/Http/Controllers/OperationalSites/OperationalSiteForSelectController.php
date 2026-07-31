<?php

namespace App\Http\Controllers\OperationalSites;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\OperationalSites\OperationalSiteForSelectRequest;
use App\Http\Resources\OperationalSiteForSelectResource;
use App\Services\OperationalSiteService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/operational-sites/for-select — minimal, searchable, paginated
 * operational-site list feeding the user-form "site" select (spec 0015,
 * ADR 0011 the for-select standard), mirroring UserForSelectController.
 *
 * Thin invokable controller: validation
 * (OperationalSiteForSelectRequest), Service call, paginated response.
 * The query/search/hydration logic lives in
 * OperationalSiteService::forSelect, not here. No permission gate
 * beyond `auth:sanctum` (ADR 0011, amended 2026-07-31): option lists
 * feed forms whose actor may legitimately lack browse rights on the
 * source module.
 *
 * @see OperationalSiteService::forSelect
 */
class OperationalSiteForSelectController extends BaseApiController
{
    public function __construct(private readonly OperationalSiteService $service) {}

    public function __invoke(OperationalSiteForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData(), $request->businessFunctionId());

            return $this->paginatedResponse(
                OperationalSiteForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
