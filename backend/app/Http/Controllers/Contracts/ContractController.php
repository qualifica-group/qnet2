<?php

namespace App\Http\Controllers\Contracts;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Contracts\UpdateContractRequest;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\User;
use App\Services\ContractService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Show/update endpoints for the `contracts` resource (spec 0072, MT-02). No
 * store/destroy (D-6): a contract is never created or deleted by hand.
 *
 * Thin controller: FormRequest validation, server-side authorization
 * (ContractPolicy), Service call, response. show/update also attach the
 * `permissions` metadata block (spec 0004) via ResourcePermissionsBuilder,
 * contextual to the returned contract.
 *
 * @see ContractService
 */
class ContractController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ContractService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/contracts/{contract}.
     */
    public function show(Request $request, Contract $contract): JsonResponse
    {
        try {
            $this->authorize('view', $contract);

            return $this->okWithPermissions(
                new ContractResource($this->service->loadDetail($contract)),
                $this->buildPermissions($request->user(), $contract),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['contract' => $contract->id]);
        }
    }

    /**
     * PUT/PATCH /api/contracts/{contract} — partial update of the editable
     * contract data.
     */
    public function update(UpdateContractRequest $request, Contract $contract): JsonResponse
    {
        try {
            $this->authorize('update', $contract);

            $contract = $this->service->update($contract, $request->toData());

            return $this->okWithPermissions(
                new ContractResource($contract),
                $this->buildPermissions($request->user(), $contract),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['contract' => $contract->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Contract $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('contracts'), $actor, $model);
    }
}
