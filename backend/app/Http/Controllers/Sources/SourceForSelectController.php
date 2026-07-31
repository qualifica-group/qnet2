<?php

namespace App\Http\Controllers\Sources;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Sources\SourceForSelectRequest;
use App\Http\Resources\SourceForSelectResource;
use App\Services\SourceService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/sources/for-select — minimal, searchable, paginated source list
 * feeding entity-backed selects (spec 0018, ADR 0011 the for-select
 * standard), mirroring ReferentTypeForSelectController.
 *
 * Thin invokable controller: validation (SourceForSelectRequest),
 * Service call, paginated response. No permission gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31): option lists feed
 * forms whose actor may legitimately lack browse rights on the source
 * module.
 *
 * @see SourceService::forSelect
 */
class SourceForSelectController extends BaseApiController
{
    public function __construct(private readonly SourceService $service) {}

    public function __invoke(SourceForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                SourceForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
