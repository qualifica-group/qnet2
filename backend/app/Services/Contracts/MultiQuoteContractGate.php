<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Exceptions\Contracts\MultiQuoteContractsRefusedException;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Loads the contracts selected for "Programma commessa unica" (spec 0267, D-3)
 * and applies the doubled "Programma" gate to EACH of them: the actor's policy
 * `program` (403 for the whole request on a single failure, so nothing is read
 * or created on partial permission), then ContractActionAvailability::mayProgram
 * and the multi-offer eligibility (D-14), each a 422 on `contract_ids` naming
 * the contracts to remove from the selection (MultiQuoteContractsRefusedException).
 */
final class MultiQuoteContractGate
{
    public function __construct(private readonly ContractActionAvailability $availability) {}

    /**
     * @param  array<int, int|string>  $contractIds
     * @return Collection<int, Contract> in the order received
     */
    public function load(User $actor, array $contractIds): Collection
    {
        $ids = array_map('intval', $contractIds);

        $contracts = Contract::query()
            ->with(['quote.opportunity.registry', 'contractStatus'])
            ->withMultiQuoteWorkOrderFlag()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $ordered = collect($ids)->map(static fn (int $id): Contract => $contracts->get($id))->values();

        // Step 1: the actor may program every contract (403)
        foreach ($ordered as $contract) {
            Gate::forUser($actor)->authorize('program', $contract);
        }

        // Step 2: every contract must be in a status a work order is programmed from (422)
        $this->refuse(
            $ordered->reject(fn (Contract $contract): bool => $this->availability->mayProgram($contract)),
            MultiQuoteContractsRefusedException::REASON_NOT_WON,
            'work_orders.multi_quote.contracts_not_won',
        );

        // Step 3: every contract must allow the multi-offer work order (422)
        $this->refuse(
            $ordered->reject(static fn (Contract $contract): bool => $contract->allowsMultiQuoteWorkOrder()),
            MultiQuoteContractsRefusedException::REASON_NOT_ALLOWED,
            'work_orders.multi_quote.contracts_not_allowed',
        );

        return $ordered;
    }

    /**
     * @param  Collection<int, Contract>  $refused
     */
    private function refuse(Collection $refused, string $reason, string $messageKey): void
    {
        if ($refused->isEmpty()) {
            return;
        }

        $contracts = $refused->map(static fn (Contract $contract): array => [
            'id' => $contract->id,
            'quote_code' => $contract->quote?->code,
            'registry_name' => $contract->quote?->opportunity?->registry?->name,
        ])->values()->all();

        // "QUO-0018 (Rossi Srl)": the offer code the grid shows, with its customer.
        $codes = collect($contracts)->map(static fn (array $contract): string => (string) $contract['quote_code']
            .($contract['registry_name'] === null ? '' : " ({$contract['registry_name']})"))->implode(', ');

        throw new MultiQuoteContractsRefusedException($reason, $contracts, __($messageKey, ['codes' => $codes]));
    }
}
