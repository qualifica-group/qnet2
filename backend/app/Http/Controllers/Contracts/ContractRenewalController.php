<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contracts;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Contracts\RenewContractRequest;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\ContractRenewal;
use App\Models\User;
use App\Services\Contracts\ContractActionAvailability;
use App\Services\Contracts\ContractRenewalService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * POST /api/contracts/{contract}/renewals and
 * DELETE /api/contracts/{contract}/renewals/{renewal} (spec 0358, D-10/D-11).
 * Same doubled gate as "Programma": ContractPolicy::program (perimeter
 * included) ANDed with ContractActionAvailability::mayProgram.
 */
class ContractRenewalController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ContractRenewalService $service,
        private readonly ContractActionAvailability $availability,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function store(RenewContractRequest $request, Contract $contract): JsonResponse
    {
        try {
            $this->authorizeRenewal($contract);

            /** @var User $actor */
            $actor = $request->user();
            $contract = $this->service->renew(
                $contract,
                (string) $request->validated('expiry_date'),
                $request->validated('renewal_date'),
                $actor,
            );

            return $this->okWithPermissions(
                new ContractResource($contract),
                $this->buildPermissions($actor, $contract),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['contract' => $contract->id]);
        }
    }

    public function destroy(Request $request, Contract $contract, ContractRenewal $renewal): JsonResponse
    {
        try {
            $this->authorizeRenewal($contract);

            /** @var User $actor */
            $actor = $request->user();
            $contract = $this->service->undo($contract, $renewal);

            return $this->okWithPermissions(
                new ContractResource($contract),
                $this->buildPermissions($actor, $contract),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['contract' => $contract->id]);
        }
    }

    private function authorizeRenewal(Contract $contract): void
    {
        $this->authorize('program', $contract);
        abort_unless($this->availability->mayProgram($contract), 403, __('You cannot program this contract: it is not validated, or it is suspended or closed negatively.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Contract $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('contracts'), $actor, $model);
    }
}
