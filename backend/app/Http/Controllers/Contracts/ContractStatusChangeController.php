<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contracts;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Contracts\ChangeContractStatusRequest;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\User;
use App\Services\ContractActionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/contracts/{contract}/change-status (user directive 2026-08-31
 * rev.2): the endpoint behind the `contracts.changeStatus` ability, which
 * until now had a Policy method and a row-action key but no route of its own.
 * Invokable, single action: no other verb exists on this route.
 */
class ContractStatusChangeController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ContractActionService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function __invoke(ChangeContractStatusRequest $request, Contract $contract): JsonResponse
    {
        try {
            $this->authorize('changeStatus', $contract);

            $contract = $this->service->changeStatus($contract, $request->toData());

            /** @var User $actor */
            $actor = $request->user();

            return $this->okWithPermissions(
                new ContractResource($contract),
                $this->buildPermissions($actor, $contract),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['contract' => $contract->id]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Contract $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('contracts'), $actor, $model);
    }
}
