<?php

namespace App\Http\Controllers\ContractTypes;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ContractTypes\StoreContractTypeRequest;
use App\Http\Requests\ContractTypes\UpdateContractTypeRequest;
use App\Http\Resources\ContractTypeResource;
use App\Models\ContractType;
use App\Models\User;
use App\Services\ContractTypeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `contract-types` resource (spec 0290 D-1). Thin
 * controller: validation (FormRequest), authorization (ContractTypePolicy),
 * Service call, response with the `permissions` block (spec 0004).
 *
 * @see ContractTypeService
 */
class ContractTypeController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ContractTypeService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function show(Request $request, ContractType $contractType): JsonResponse
    {
        try {
            $this->authorize('view', $contractType);

            return $this->withPermissions($request->user(), $contractType);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['contractType' => $contractType->id]);
        }
    }

    public function store(StoreContractTypeRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', ContractType::class);

            $contractType = $this->service->create($request->toAttributes());

            return $this->withPermissions($request->user(), $contractType, 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateContractTypeRequest $request, ContractType $contractType): JsonResponse
    {
        try {
            $this->authorize('update', $contractType);

            $contractType = $this->service->update($contractType, $request->toAttributes());

            return $this->withPermissions($request->user(), $contractType);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['contractType' => $contractType->id]);
        }
    }

    public function destroy(ContractType $contractType): JsonResponse
    {
        try {
            $this->authorize('delete', $contractType);

            $this->service->delete($contractType);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['contractType' => $contractType->id]);
        }
    }

    private function withPermissions(User $actor, ContractType $contractType, string $message = 'OK', HttpStatusEnum $status = HttpStatusEnum::OK): JsonResponse
    {
        return $this->okWithPermissions(
            (new ContractTypeResource($contractType))->resolve(),
            $this->permissionsBuilder->build($this->authorization->resolve('contract-types'), $actor, $contractType),
            $message,
            $status,
        );
    }
}
