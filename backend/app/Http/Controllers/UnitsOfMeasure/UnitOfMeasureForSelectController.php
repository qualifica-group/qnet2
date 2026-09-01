<?php

namespace App\Http\Controllers\UnitsOfMeasure;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\UnitsOfMeasure\UnitOfMeasureForSelectRequest;
use App\Http\Resources\UnitOfMeasureForSelectResource;
use App\Services\UnitOfMeasureService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/units-of-measure/for-select — minimal, searchable, paginated
 * unit of measure list feeding entity-backed selects (spec 0088, ADR 0011
 * the for-select standard).
 *
 * Thin invokable controller: validation (UnitOfMeasureForSelectRequest),
 * Service call, paginated response. No permission gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31): option lists feed forms
 * whose actor may legitimately lack browse rights on the source module.
 *
 * @see UnitOfMeasureService::forSelect
 */
class UnitOfMeasureForSelectController extends BaseApiController
{
    public function __construct(private readonly UnitOfMeasureService $service) {}

    public function __invoke(UnitOfMeasureForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                UnitOfMeasureForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
