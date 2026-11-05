<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractRenewal;
use App\Models\User;
use App\Services\ContractService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Rinnova" / "Annulla rinnovo" (spec 0358, D-10/D-11): opens or reverts a
 * renewal period of a contract. Both are a single transaction that writes the
 * history row and the contract dates together; the contract status never
 * changes. The response is the same detail read as GET /contracts/{contract}.
 */
class ContractRenewalService
{
    public function __construct(
        private readonly ContractService $contractService,
        private readonly ContractActionAvailability $availability,
    ) {}

    public function renew(Contract $contract, string $expiryDate, ?string $renewalDate, User $actor): Contract
    {
        DB::transaction(function () use ($contract, $expiryDate, $renewalDate, $actor): void {
            // Step 1: lock the contract so two renewals can never share a number
            $locked = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            $this->assertExpiryAfterCurrent($locked, $expiryDate);

            // Step 2: write the history row with the dates being replaced
            $previousExpiry = $locked->expiry_date;
            $renewal = new ContractRenewal([
                'contract_id' => $locked->id,
                'number' => $locked->currentRenewalNumber() + 1,
                'starts_on' => $previousExpiry?->copy()->addDay()->toDateString() ?? Carbon::today()->toDateString(),
                'previous_renewal_date' => $locked->renewal_date?->toDateString(),
                'previous_expiry_date' => $previousExpiry?->toDateString(),
                'renewal_date' => $renewalDate,
                'expiry_date' => $expiryDate,
            ]);
            $renewal->renewed_by = $actor->id;
            $renewal->save();

            // Step 3: move the contract to the new dates
            $locked->update(['renewal_date' => $renewalDate, 'expiry_date' => $expiryDate]);
        });

        return $this->detail($contract);
    }

    public function undo(Contract $contract, ContractRenewal $renewal): Contract
    {
        DB::transaction(function () use ($contract, $renewal): void {
            // Step 1: lock the contract and re-check the rules under the lock
            $locked = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            $this->assertUndoable($locked, $renewal);

            // Step 2: restore the dates saved in the history and drop the row
            $locked->update([
                'renewal_date' => $renewal->previous_renewal_date?->toDateString(),
                'expiry_date' => $renewal->previous_expiry_date?->toDateString(),
            ]);
            $renewal->delete();
        });

        return $this->detail($contract);
    }

    private function assertExpiryAfterCurrent(Contract $contract, string $expiryDate): void
    {
        if ($contract->expiry_date !== null && Carbon::parse($expiryDate)->lte($contract->expiry_date)) {
            throw ValidationException::withMessages([
                'expiry_date' => __('The new expiry date must be after the current one.'),
            ]);
        }
    }

    private function assertUndoable(Contract $contract, ContractRenewal $renewal): void
    {
        if ($renewal->number !== $contract->currentRenewalNumber()) {
            throw ValidationException::withMessages(['renewal' => __('Only the latest renewal can be undone.')]);
        }

        if (! $this->availability->mayUndoRenewal($contract, $renewal)) {
            throw ValidationException::withMessages(['renewal' => __('The renewal period already has work orders.')]);
        }
    }

    private function detail(Contract $contract): Contract
    {
        return $this->contractService->loadDetail($contract->fresh()->unsetRelation('renewals'));
    }
}
