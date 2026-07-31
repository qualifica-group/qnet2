<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Projects\ProjectForSelectRequest;
use App\Http\Resources\ProjectForSelectResource;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/projects/for-select — minimal, searchable, paginated project list
 * feeding entity-backed selects (spec 0023, ADR 0011), mirroring
 * ReferentForSelectController. `meta` carries the Campaign form's defaults
 * (registry/partner/pipeline_status/business_function/state/
 * product_category + budget figures).
 *
 * Thin invokable controller: validation (ProjectForSelectRequest),
 * Service call, paginated response. No permission gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31): option lists feed
 * forms whose actor may legitimately lack browse rights on the source
 * module.
 *
 * @see ProjectService::forSelect
 */
class ProjectForSelectController extends BaseApiController
{
    public function __construct(private readonly ProjectService $service) {}

    public function __invoke(ProjectForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                ProjectForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
