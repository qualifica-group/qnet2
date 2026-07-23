<?php

namespace App\Http\Controllers\RewardTypes;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\RewardTypes\StoreRewardTypeRequest;
use App\Http\Requests\RewardTypes\UpdateRewardTypeRequest;
use App\Http\Resources\RewardTypeResource;
use App\Models\RewardType;
use App\Models\User;
use App\Services\RewardTypeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `reward-types` resource (spec 0058), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (RewardTypePolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see RewardTypeService
 */
class RewardTypeController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly RewardTypeService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/reward-types/{rewardType} — single reward type (view
     * row-action).
     */
    public function show(Request $request, RewardType $rewardType): JsonResponse
    {
        try {
            $this->authorize('view', $rewardType);

            $rewardType = $this->service->loadDetail($rewardType);

            return $this->okWithPermissions(
                new RewardTypeResource($rewardType),
                $this->buildPermissions($request->user(), $rewardType),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['rewardType' => $rewardType->id]);
        }
    }

    /**
     * POST /api/reward-types — create a new reward type.
     */
    public function store(StoreRewardTypeRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', RewardType::class);

            $rewardType = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new RewardTypeResource($rewardType),
                $this->buildPermissions($request->user(), $rewardType),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/reward-types/{rewardType} — update an existing reward
     * type.
     */
    public function update(UpdateRewardTypeRequest $request, RewardType $rewardType): JsonResponse
    {
        try {
            $this->authorize('update', $rewardType);

            $rewardType = $this->service->update($rewardType, $request->toData());

            return $this->okWithPermissions(
                new RewardTypeResource($rewardType),
                $this->buildPermissions($request->user(), $rewardType),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['rewardType' => $rewardType->id]);
        }
    }

    /**
     * DELETE /api/reward-types/{rewardType} — delete a reward type (BR-3: no
     * delete-guard exists yet, no entity references reward_types).
     */
    public function destroy(RewardType $rewardType): JsonResponse
    {
        try {
            $this->authorize('delete', $rewardType);

            $this->service->delete($rewardType);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['rewardType' => $rewardType->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?RewardType $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('reward-types'), $actor, $model);
    }
}
