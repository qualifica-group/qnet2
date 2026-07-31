<?php

namespace App\Http\Controllers\BusinessFunctions;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\BusinessFunctions\BusinessFunctionForSelectRequest;
use App\Http\Resources\BusinessFunctionForSelectResource;
use App\Services\BusinessFunctionService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/business-functions/for-select — minimal, searchable, paginated
 * business-function list feeding the user-form "function" select (spec 0015,
 * ADR 0011 the for-select standard), mirroring UserForSelectController.
 *
 * Thin invokable controller: validation
 * (BusinessFunctionForSelectRequest), Service call, paginated response.
 * The query/search/hydration logic lives in
 * BusinessFunctionService::forSelect, not here. No permission gate
 * beyond `auth:sanctum` (ADR 0011, amended 2026-07-31): option lists
 * feed forms whose actor may legitimately lack browse rights on the
 * source module.
 *
 * @see BusinessFunctionService::forSelect
 */
class BusinessFunctionForSelectController extends BaseApiController
{
    public function __construct(private readonly BusinessFunctionService $service) {}

    public function __invoke(BusinessFunctionForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData(), $request->excludeDescendantsOf());

            return $this->paginatedResponse(
                BusinessFunctionForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
