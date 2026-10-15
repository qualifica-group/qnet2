<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

use App\Enums\CommissionSettlementStatus;
use App\Models\CommissionSettlement;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use App\Services\RegistryService;
use Illuminate\Validation\ValidationException;

/**
 * The supplier registry an agent is paid through (spec 0267, D-7): candidates
 * (registries with the same tax code or VAT number as the agent), link of an
 * existing one, or creation from the agent card. Linking runs on the
 * settlement lock and only while the document is received.
 */
class AgentSupplierService
{
    public function __construct(
        private readonly CommissionSettlementService $settlements,
        private readonly RegistryService $registries,
        private readonly AgentSupplierCard $card,
    ) {}

    /**
     * Registries whose card has the same non-empty tax code or VAT number as the agent.
     *
     * @return array<int, array{id: int, name: string, tax_code: string|null, vat_number: string|null, is_supplier: bool}>
     */
    public function candidates(Referent $referent): array
    {
        $taxCode = trim((string) $referent->personalData?->tax_code);
        $vatNumber = trim((string) $referent->personalData?->vat_number);

        if ($taxCode === '' && $vatNumber === '') {
            return [];
        }

        return Registry::query()
            ->with('personalData')
            ->whereHas('personalData', function ($card) use ($taxCode, $vatNumber): void {
                $card->where(function ($match) use ($taxCode, $vatNumber): void {
                    $taxCode === '' || $match->orWhere('tax_code', $taxCode);
                    $vatNumber === '' || $match->orWhere('vat_number', $vatNumber);
                });
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Registry $registry): array => [
                'id' => $registry->id,
                'name' => $registry->name,
                'tax_code' => $registry->personalData->tax_code,
                'vat_number' => $registry->personalData->vat_number,
                'is_supplier' => (bool) $registry->is_supplier,
            ])->values()->all();
    }

    /**
     * Links an existing supplier registry, or creates one from the agent card
     * when `$registryId` is null, and stores it on the agent.
     */
    public function link(CommissionSettlement $settlement, ?int $registryId, User $actor): Registry
    {
        $registry = null;

        $this->settlements->transition($settlement, function (CommissionSettlement $locked) use ($registryId, $actor, &$registry): void {
            // Step 1: state and the agent with its card
            $this->settlements->assertDocumentReceived($locked);
            $referent = Referent::query()->with(['personalData.contacts', 'personalData.addresses'])->findOrFail($locked->referent_id);

            // Step 2: the agent already paid through another supplier keeps it
            $this->assertChangeAllowed($referent, $registryId);

            // Step 3: the registry to link
            $registry = $registryId === null ? $this->createFromAgent($referent, $actor) : $this->existingSupplier($registryId);
            $referent->update(['supplier_registry_id' => $registry->id]);
        });

        return $registry;
    }

    private function assertChangeAllowed(Referent $referent, ?int $registryId): void
    {
        if ($referent->supplier_registry_id === null || (int) $referent->supplier_registry_id === $registryId) {
            return;
        }

        $approved = CommissionSettlement::query()
            ->where('referent_id', $referent->id)
            ->where('status', CommissionSettlementStatus::Approved)
            ->exists();

        if ($approved) {
            throw ValidationException::withMessages(['registry_id' => [__('The agent has approved settlements: the supplier registry cannot be changed.')]]);
        }
    }

    private function existingSupplier(int $registryId): Registry
    {
        $registry = Registry::query()->findOrFail($registryId);

        if (! $registry->is_supplier) {
            throw ValidationException::withMessages(['registry_id' => [__('The registry is not a supplier.')]]);
        }

        return $registry;
    }

    private function createFromAgent(Referent $referent, User $actor): Registry
    {
        $missing = $this->card->missingFields($referent);

        if ($missing !== []) {
            throw ValidationException::withMessages(['registry_id' => [__('The agent card is incomplete, missing: :fields.', ['fields' => implode(', ', $missing)])]]);
        }

        return $this->registries->create($actor, $this->card->registryData(), $this->card->profile($referent));
    }
}
