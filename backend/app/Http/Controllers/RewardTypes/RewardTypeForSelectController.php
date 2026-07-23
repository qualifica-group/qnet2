<?php

namespace App\Http\Controllers\RewardTypes;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\RewardTypes\RewardTypeForSelectRequest;
use App\Http\Resources\RewardTypeForSelectResource;
use App\Models\RewardType;
use App\Services\RewardTypeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/reward-types/for-select — minimal, searchable, paginated reward
 * type list feeding entity-backed selects (spec 0058 D-7, ADR 0011 the
 * for-select standard).
 *
 * Thin invokable controller: validation (RewardTypeForSelectRequest),
 * server-side authorization (reward-types.viewAny via RewardTypePolicy),
 * Service call, paginated response.
 *
 * @see RewardTypeService::forSelect
 */
class RewardTypeForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly RewardTypeService $service) {}

    public function __invoke(RewardTypeForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', RewardType::class);

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
