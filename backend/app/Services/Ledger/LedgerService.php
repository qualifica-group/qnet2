<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\DataObjects\Ledger\LedgerFilters;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Registry ledger (spec 0266, "Partitario"): the customer and the supplier
 * section of one registry. A section is null when the actor lacks the module
 * permission behind it (defense in depth on top of `ledger.view`) or, for the
 * supplier, when the registry is not a supplier and has no payable document.
 */
final class LedgerService
{
    public function __construct(
        private readonly ReceivableLedgerSource $receivable,
        private readonly PayableLedgerSource $payable,
        private readonly LedgerCalculator $calculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $actor, Registry $registry, LedgerFilters $filters): array
    {
        // Step 1: "today" is read once, so every section ages against the same day
        $today = Carbon::today()->toImmutable();

        // Step 2: one section per side the actor may see
        $customer = $actor->can('invoices.view')
            ? $this->calculator->section($this->receivable->entries($registry, $filters), LedgerSide::Customer, $filters->dateFrom, $filters->dateTo, $today)
            : null;

        $supplier = $this->showsSupplier($actor, $registry)
            ? $this->calculator->section($this->payable->entries($registry, $filters), LedgerSide::Supplier, $filters->dateFrom, $filters->dateTo, $today)
            : null;

        // Step 3: the response shape of the contract
        return [
            'registry' => ['id' => $registry->id, 'name' => $registry->name, 'is_supplier' => (bool) $registry->is_supplier],
            'today' => $today->toDateString(),
            'customer' => $customer,
            'supplier' => $supplier,
        ];
    }

    private function showsSupplier(User $actor, Registry $registry): bool
    {
        return $actor->can('purchase-invoices.view')
            && ($registry->is_supplier || $this->payable->hasDocuments($registry));
    }
}
