<?php

namespace App\Http\Controllers\RewardStatuses;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\RewardStatuses\RewardStatusForSelectRequest;
use App\Http\Resources\RewardStatusForSelectResource;
use App\Services\RewardStatusService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/reward-statuses/for-select — minimal, searchable, paginated
 * reward status list feeding entity-backed selects (spec 0060 BR-5, ADR 0011
 * the for-select standard).
 *
 * Thin invokable controller: validation (RewardStatusForSelectRequest),
 * Service call, paginated response. No permission gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31): option lists feed
 * forms whose actor may legitimately lack browse rights on the source
 * module.
 *
 * @see RewardStatusService::forSelect
 */
class RewardStatusForSelectController extends BaseApiController
{
    public function __construct(private readonly RewardStatusService $service) {}

    public function __invoke(RewardStatusForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                RewardStatusForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
