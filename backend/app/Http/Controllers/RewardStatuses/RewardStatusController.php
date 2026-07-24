<?php

namespace App\Http\Controllers\RewardStatuses;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\RewardStatuses\StoreRewardStatusRequest;
use App\Http\Requests\RewardStatuses\UpdateRewardStatusRequest;
use App\Http\Requests\Statuses\ReorderStatusesRequest;
use App\Http\Resources\RewardStatusResource;
use App\Models\RewardStatus;
use App\Models\User;
use App\Services\RewardStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `reward-statuses` resource (spec 0060), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (RewardStatusPolicy), Service call, response. No business logic, no
 * queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see RewardStatusService
 */
class RewardStatusController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly RewardStatusService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/reward-statuses/{rewardStatus} — single reward status (view
     * row-action).
     */
    public function show(Request $request, RewardStatus $rewardStatus): JsonResponse
    {
        try {
            $this->authorize('view', $rewardStatus);

            $rewardStatus = $this->service->loadDetail($rewardStatus);

            return $this->okWithPermissions(
                new RewardStatusResource($rewardStatus),
                $this->buildPermissions($request->user(), $rewardStatus),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['rewardStatus' => $rewardStatus->id]);
        }
    }

    /**
     * POST /api/reward-statuses — create a new reward status.
     */
    public function store(StoreRewardStatusRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', RewardStatus::class);

            $rewardStatus = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new RewardStatusResource($rewardStatus),
                $this->buildPermissions($request->user(), $rewardStatus),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/reward-statuses/{rewardStatus} — update an existing
     * reward status.
     */
    public function update(UpdateRewardStatusRequest $request, RewardStatus $rewardStatus): JsonResponse
    {
        try {
            $this->authorize('update', $rewardStatus);

            $rewardStatus = $this->service->update($rewardStatus, $request->toData());

            return $this->okWithPermissions(
                new RewardStatusResource($rewardStatus),
                $this->buildPermissions($request->user(), $rewardStatus),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['rewardStatus' => $rewardStatus->id]);
        }
    }

    /**
     * DELETE /api/reward-statuses/{rewardStatus} — delete a reward status
     * (BR-4: 409 if referenced by a Reward).
     */
    public function destroy(RewardStatus $rewardStatus): JsonResponse
    {
        try {
            $this->authorize('delete', $rewardStatus);

            $this->service->delete($rewardStatus);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['rewardStatus' => $rewardStatus->id]);
        }
    }

    /**
     * POST /api/reward-statuses/reorder — resequence the custom rows (D-3).
     * Gated on `reward-statuses.update` directly (no single Model instance
     * exists for a bulk reorder, so there is no Policy `update($user, $model)`
     * to delegate to — mirrors ExportController's `export` ability check).
     */
    public function reorder(ReorderStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize('reward-statuses.update');

            $reordered = $this->service->reorder($request->orderedIds());

            return $this->ok($reordered->map(static fn (RewardStatus $status): array => [
                'id' => $status->id,
                'sort_order' => $status->sort_order,
                'system_key' => $status->system_key,
            ])->all());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?RewardStatus $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('reward-statuses'), $actor, $model);
    }
}
