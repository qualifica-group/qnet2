<?php

namespace App\Http\Controllers\Treasury;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Treasury\StoreTreasuryMovementRequest;
use App\Http\Requests\Treasury\UpdateTreasuryMovementRequest;
use App\Http\Resources\TreasuryMovementResource;
use App\Models\TreasuryMovement;
use App\Models\User;
use App\Services\Treasury\TreasuryMovementService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `treasury-movements` resource (spec 0355). Thin
 * controller: FormRequest validation, TreasuryMovementPolicy, Service call.
 *
 * @see TreasuryMovementService
 */
class TreasuryMovementController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TreasuryMovementService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function show(Request $request, TreasuryMovement $treasuryMovement): JsonResponse
    {
        try {
            $this->authorize('view', $treasuryMovement);

            return $this->respond($request->user(), $this->service->detail($treasuryMovement));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['treasuryMovement' => $treasuryMovement->id]);
        }
    }

    public function store(StoreTreasuryMovementRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', TreasuryMovement::class);

            $movement = $this->service->create($request->safe()->all(), $request->user());

            return $this->respond($request->user(), $movement, 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateTreasuryMovementRequest $request, TreasuryMovement $treasuryMovement): JsonResponse
    {
        try {
            $this->authorize('update', $treasuryMovement);

            $movement = $this->service->update($treasuryMovement, $request->safe()->all());

            return $this->respond($request->user(), $movement);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['treasuryMovement' => $treasuryMovement->id]);
        }
    }

    public function destroy(TreasuryMovement $treasuryMovement): JsonResponse
    {
        try {
            $this->authorize('delete', $treasuryMovement);

            $this->service->delete($treasuryMovement);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['treasuryMovement' => $treasuryMovement->id]);
        }
    }

    private function respond(User $actor, TreasuryMovement $movement, string $message = 'OK', HttpStatusEnum $status = HttpStatusEnum::OK): JsonResponse
    {
        return $this->okWithPermissions(
            new TreasuryMovementResource($movement),
            $this->permissionsBuilder->build($this->authorization->resolve('treasury-movements'), $actor, $movement),
            $message,
            $status,
        );
    }
}
