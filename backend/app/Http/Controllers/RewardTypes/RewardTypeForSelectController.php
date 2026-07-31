<?php

namespace App\Http\Controllers\RewardTypes;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\RewardTypes\RewardTypeForSelectRequest;
use App\Http\Resources\RewardTypeForSelectResource;
use App\Services\RewardTypeService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/reward-types/for-select — minimal, searchable, paginated reward
 * type list feeding entity-backed selects (spec 0058 D-7, ADR 0011 the
 * for-select standard).
 *
 * Thin invokable controller: validation (RewardTypeForSelectRequest),
 * Service call, paginated response. No permission gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31): option lists feed
 * forms whose actor may legitimately lack browse rights on the source
 * module.
 *
 * @see RewardTypeService::forSelect
 */
class RewardTypeForSelectController extends BaseApiController
{
    public function __construct(private readonly RewardTypeService $service) {}

    public function __invoke(RewardTypeForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                RewardTypeForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
