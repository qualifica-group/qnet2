<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contracts;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Contracts\ScheduleContractRequest;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\User;
use App\Services\ContractActionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/contracts/{contract}/schedule (spec 0072). Invokable, single
 * action: no other verb exists on this route.
 */
class ContractScheduleController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ContractActionService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function __invoke(ScheduleContractRequest $request, Contract $contract): JsonResponse
    {
        try {
            $this->authorize('schedule', $contract);

            /** @var User $actor */
            $actor = $request->user();
            $contract = $this->service->schedule($contract, $request->toData());

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
