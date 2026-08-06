<?php

declare(strict_types=1);

namespace App\Http\Controllers\ContractStatuses;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ContractStatuses\StoreContractStatusRequest;
use App\Http\Requests\ContractStatuses\UpdateContractStatusRequest;
use App\Http\Requests\Statuses\ReorderStatusesRequest;
use App\Http\Resources\ContractStatusResource;
use App\Models\ContractStatus;
use App\Models\User;
use App\Services\ContractStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `contract-statuses` resource (spec 0072), backing
 * the backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (ContractStatusPolicy), Service call, response. No business logic, no
 * queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see ContractStatusService
 */
class ContractStatusController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ContractStatusService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/contract-statuses/{contractStatus} — single contract status
     * (view row-action).
     */
    public function show(Request $request, ContractStatus $contractStatus): JsonResponse
    {
        try {
            $this->authorize('view', $contractStatus);

            $contractStatus = $this->service->loadDetail($contractStatus);

            return $this->okWithPermissions(
                new ContractStatusResource($contractStatus),
                $this->buildPermissions($request->user(), $contractStatus),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['contractStatus' => $contractStatus->id]);
        }
    }

    /**
     * POST /api/contract-statuses — create a new contract status.
     */
    public function store(StoreContractStatusRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', ContractStatus::class);

            $contractStatus = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new ContractStatusResource($contractStatus),
                $this->buildPermissions($request->user(), $contractStatus),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/contract-statuses/{contractStatus} — update an existing
     * contract status.
     */
    public function update(UpdateContractStatusRequest $request, ContractStatus $contractStatus): JsonResponse
    {
        try {
            $this->authorize('update', $contractStatus);

            $contractStatus = $this->service->update($contractStatus, $request->toData());

            return $this->okWithPermissions(
                new ContractStatusResource($contractStatus),
                $this->buildPermissions($request->user(), $contractStatus),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['contractStatus' => $contractStatus->id]);
        }
    }

    /**
     * DELETE /api/contract-statuses/{contractStatus} — delete a contract
     * status (409 if referenced by a Contract).
     */
    public function destroy(ContractStatus $contractStatus): JsonResponse
    {
        try {
            $this->authorize('delete', $contractStatus);

            $this->service->delete($contractStatus);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['contractStatus' => $contractStatus->id]);
        }
    }

    /**
     * POST /api/contract-statuses/reorder — resequence the custom rows.
     * Gated on `contract-statuses.update` directly (no single Model instance
     * exists for a bulk reorder, so there is no Policy `update($user,
     * $model)` to delegate to — mirrors RewardStatusController::reorder).
     */
    public function reorder(ReorderStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize('contract-statuses.update');

            $reordered = $this->service->reorder($request->orderedIds());

            return $this->ok($reordered->map(static fn (ContractStatus $status): array => [
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
    private function buildPermissions(User $actor, ?ContractStatus $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('contract-statuses'), $actor, $model);
    }
}
