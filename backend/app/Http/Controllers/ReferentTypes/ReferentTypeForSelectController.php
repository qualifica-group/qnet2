<?php

namespace App\Http\Controllers\ReferentTypes;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ReferentTypes\ReferentTypeForSelectRequest;
use App\Http\Resources\ReferentTypeForSelectResource;
use App\Services\ReferentTypeService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/referent-types/for-select — minimal, searchable, paginated
 * referent-type list feeding the referent-form "Referent type" select (spec
 * 0016, ADR 0011 the for-select standard), mirroring
 * BusinessFunctionForSelectController.
 *
 * Thin invokable controller: validation (ReferentTypeForSelectRequest),
 * Service call, paginated response. No permission gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31): option lists feed
 * forms whose actor may legitimately lack browse rights on the source
 * module.
 *
 * @see ReferentTypeService::forSelect
 */
class ReferentTypeForSelectController extends BaseApiController
{
    public function __construct(private readonly ReferentTypeService $service) {}

    public function __invoke(ReferentTypeForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                ReferentTypeForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
