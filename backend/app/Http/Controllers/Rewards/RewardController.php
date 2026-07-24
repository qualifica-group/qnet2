<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rewards;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Rewards\UpdateRewardStatusRequest;
use App\Http\Resources\RewardResource;
use App\Models\Reward;
use App\Services\RewardService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * `PATCH /api/rewards/{reward}` — the card's inline status edit (spec 0060
 * §4, D-1). Gated on `rewarded-referents.update` directly (D-8: no dedicated
 * Reward model policy — the module that owns the card is the authorization
 * boundary, same ability-string precedent as
 * OpportunityStatusController::reorder). No dedicated `reward_status`
 * field-permission is introduced (D-8): this resource has no MetaField
 * surface consuming one.
 *
 * Thin controller: authz + FormRequest validation + Service + Resource
 * output, mirroring the full item shape `GET /api/referents/{referent}/
 * rewards` already returns (RewardResource::eagerLoad() keeps both call
 * sites' eager-load spec identical, AC-017).
 */
class RewardController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly RewardService $service) {}

    public function updateStatus(UpdateRewardStatusRequest $request, Reward $reward): JsonResponse
    {
        try {
            $this->authorize('rewarded-referents.update');

            $reward = $this->service->updateStatus($reward, $request->rewardStatusId());
            $reward->load(RewardResource::eagerLoad());

            return $this->ok(new RewardResource($reward));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['reward' => $reward->id]);
        }
    }
}
