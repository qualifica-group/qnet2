<?php

namespace App\Http\Controllers\RewardStatuses;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\RewardStatuses\RewardStatusForSelectRequest;
use App\Http\Resources\RewardStatusForSelectResource;
use App\Models\RewardStatus;
use App\Services\RewardStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/reward-statuses/for-select — minimal, searchable, paginated
 * reward status list feeding entity-backed selects (spec 0060 BR-5, ADR 0011
 * the for-select standard).
 *
 * Thin invokable controller: validation (RewardStatusForSelectRequest),
 * server-side authorization (reward-statuses.viewAny via RewardStatusPolicy),
 * Service call, paginated response.
 *
 * @see RewardStatusService::forSelect
 */
class RewardStatusForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly RewardStatusService $service) {}

    public function __invoke(RewardStatusForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', RewardStatus::class);

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
