<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Contracts\ChangeContractStatusData;
use App\DataObjects\Contracts\TerminateContractData;
use App\DataObjects\Contracts\ValidateContractData;
use App\Enums\ContractStatusGroup;
use App\Enums\StatusSystemKey;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\User;
use App\Services\Contracts\ContractStatusResolver;
use Illuminate\Support\Facades\DB;

/**
 * The domain actions that move a contract FORWARD (spec 0072, BR-3/BR-4,
 * plus "Modifica stato" — user directive 2026-08-31 rev.2): validate,
 * changeStatus, terminate. "Riattiva contratto", the only action
 * with two paths, lives in its own class
 * (App\Services\Contracts\ContractReactivator). Each is a single, small write inside its
 * own transaction, followed by the SAME detail read ContractController::show
 * uses (ContractService::loadDetail()), so every action's response is
 * identical in shape to a plain GET.
 */
class ContractActionService
{
    public function __construct(
        private readonly ContractService $contractService,
        private readonly ContractStatusResolver $statusResolver,
    ) {}

    /**
     * BR-3: not repeatable (422 if already validated), refused while
     * suspended (422) — a suspended contract has no working status to
     * validate into. The destination status defaults to the system
     * "Validato" row (user directive 2026-08-31) and, when the client sends
     * one, MUST belong to the closed_won group — the mirror image of
     * terminate()'s closed_lost rule, and what makes "validato" and "stato
     * con chiusura positiva" the same fact for the action bar
     * (ContractActionAvailability).
     */
    public function validate(Contract $contract, ValidateContractData $data, User $actor): Contract
    {
        DB::transaction(function () use ($contract, $data, $actor): void {
            $this->assertNotClosed($contract);
            $this->assertNotSuspended($contract);

            $validatedAt = $data->validatedAtSubmitted ? $data->validatedAt : now()->toDateString();

            $statusId = $data->contractStatusIdSubmitted
                ? $data->contractStatusId
                : $this->statusResolver->systemId(StatusSystemKey::Validated);

            $this->assertClosedWon($statusId);

            $contract->validated_at = $validatedAt;
            $contract->validated_by = $actor->id;
            $contract->contract_status_id = $statusId;

            $contract->save();

            activity($contract->getTable())
                ->performedOn($contract)
                ->causedBy($actor)
                ->event('contract.validated')
                ->withProperties([
                    'validated_at' => $validatedAt,
                    'validated_by' => $actor->id,
                    'contract_status_id' => $contract->contract_status_id,
                ])
                ->log('Contract validated');
        });

        return $this->contractService->loadDetail($contract->fresh());
    }

    /**
     * "Modifica stato" (user directive 2026-08-31 rev.2): moves a WORKING
     * contract onto another open/pending status. Both ends are constrained —
     * the destination by ChangeContractStatusRequest, the current state here
     * — so this action can never open or close a contract: those transitions
     * belong to "Valida"/"Disdici"/"Riattiva" alone.
     */
    public function changeStatus(Contract $contract, ChangeContractStatusData $data): Contract
    {
        DB::transaction(function () use ($contract, $data): void {
            $this->assertWorking($contract);

            $contract->contract_status_id = $data->contractStatusId;
            $contract->save();

            activity($contract->getTable())
                ->performedOn($contract)
                ->event('contract.status_changed')
                ->withProperties(['contract_status_id' => $data->contractStatusId])
                ->log('Contract status changed');
        });

        return $this->contractService->loadDetail($contract->fresh());
    }

    /**
     * BR-4: not repeatable (422 if already disdetto). Default destination is
     * the system 'terminated' row (D-2); a client-provided one MUST belong
     * to the closed_lost group — enforced by TerminateContractRequest's
     * Rule::exists already, re-asserted here in depth since this method may
     * be invoked directly by any future caller.
     */
    public function terminate(Contract $contract, TerminateContractData $data, User $actor): Contract
    {
        DB::transaction(function () use ($contract, $data, $actor): void {
            $this->assertNotTerminated($contract);

            $statusId = $data->contractStatusIdSubmitted
                ? $data->contractStatusId
                : $this->statusResolver->systemId(StatusSystemKey::Terminated);

            $this->assertClosedLost($statusId);

            $contract->terminated_at = $data->terminatedAt;
            $contract->termination_reason = $data->terminationReason;
            $contract->terminated_by = $actor->id;
            $contract->contract_status_id = $statusId;
            $contract->save();

            activity($contract->getTable())
                ->performedOn($contract)
                ->causedBy($actor)
                ->event('contract.terminated')
                ->withProperties([
                    'terminated_at' => $data->terminatedAt,
                    'termination_reason' => $data->terminationReason,
                    'terminated_by' => $actor->id,
                    'contract_status_id' => $statusId,
                ])
                ->log('Contract terminated');
        });

        return $this->contractService->loadDetail($contract->fresh());
    }

    /**
     * A contract can only be validated while it is still working (open or
     * pending). Refusing on the GROUP rather than on the `validated_at`
     * stamp is what makes the flow of the 2026-08-31 rev.2 directive
     * consistent: a contract disdetto and then riattivato lands back on an
     * open/pending status and must be validatable again, even though it
     * carries the stamp of its first validation.
     */
    private function assertNotClosed(Contract $contract): void
    {
        $contract->loadMissing('contractStatus');
        $group = $contract->contractStatus?->group;

        if ($group === ContractStatusGroup::ClosedWon) {
            abort(422, 'This contract has already been validated.');
        }

        if ($group === ContractStatusGroup::ClosedLost) {
            abort(422, 'This contract is closed: reactivate it first.');
        }
    }

    private function assertWorking(Contract $contract): void
    {
        $contract->loadMissing('contractStatus');

        if (! in_array($contract->contractStatus?->group, [ContractStatusGroup::Open, ContractStatusGroup::Pending], true)) {
            abort(422, 'The status of a closed contract cannot be changed directly.');
        }
    }

    private function assertNotSuspended(Contract $contract): void
    {
        if ($contract->isSuspended()) {
            abort(422, 'This contract is suspended.');
        }
    }

    private function assertNotTerminated(Contract $contract): void
    {
        if ($contract->terminated_at !== null) {
            abort(422, 'This contract has already been terminated.');
        }
    }

    private function assertClosedWon(int $statusId): void
    {
        // See assertClosedLost(): Builder::value() already returns the cast
        // enum, not a raw string.
        $group = ContractStatus::query()->whereKey($statusId)->value('group');

        if ($group !== ContractStatusGroup::ClosedWon) {
            abort(422, 'The destination status must belong to the closed_won group.');
        }
    }

    private function assertClosedLost(int $statusId): void
    {
        // Eloquent's Builder::value() hydrates via first() and reads the
        // attribute through the model's cast pipeline, so this is already
        // the ContractStatusGroup enum, not a raw string — compare as such.
        $group = ContractStatus::query()->whereKey($statusId)->value('group');

        if ($group !== ContractStatusGroup::ClosedLost) {
            abort(422, 'The destination status must belong to the closed_lost group.');
        }
    }
}
