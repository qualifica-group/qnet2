<?php

declare(strict_types=1);

namespace App\Imports\Payslips;

use App\Models\EmploymentProfile;
use App\Models\Registry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Writes a validated plan in one transaction (spec 0431, D-1, D-3, D-4, D-10):
 * supplier registries, IBANs, then one payable document per row.
 */
final class PayslipApplier
{
    public function __construct(
        private readonly PayslipSupplierCreator $supplierCreator,
        private readonly PayslipDocumentWriter $documents,
    ) {}

    public function apply(PayslipPlan $plan, CarbonImmutable $paymentDate, User $actor): void
    {
        DB::transaction(function () use ($plan, $paymentDate, $actor): void {
            // Step 1: the supplier registry of every person
            $registryIds = $this->resolveSuppliers($plan, $actor);

            // Step 2: IBANs on the existing employment profiles
            $this->storeIbans($plan->ibanUpdates);

            // Step 3: the documents
            foreach ($plan->entries as $entry) {
                $entry->existing === null
                    ? $this->documents->create($entry, $registryIds[$entry->userId], $paymentDate, $actor)
                    : $this->documents->update($entry, $paymentDate);
            }
        });
    }

    /**
     * @return array<int, int> user id => supplier registry id
     */
    private function resolveSuppliers(PayslipPlan $plan, User $actor): array
    {
        $registryIds = [];
        $linkedIds = [];

        foreach ($plan->suppliers as $userId => $decision) {
            if ($decision['action'] === PayslipSupplierResolver::EXISTING) {
                $registryIds[$userId] = (int) $decision['registry_id'];

                continue;
            }

            $registryIds[$userId] = $decision['action'] === PayslipSupplierResolver::LINK
                ? (int) $decision['registry_id']
                : $this->supplierCreator->create($userId, $actor)->id;
            $decision['action'] === PayslipSupplierResolver::LINK && $linkedIds[] = $registryIds[$userId];

            User::query()->whereKey($userId)->update(['supplier_registry_id' => $registryIds[$userId]]);
        }

        Registry::query()->whereIn('id', $linkedIds)->where('is_supplier', false)->get()
            ->each(static fn (Registry $registry): bool => $registry->update(['is_supplier' => true]));

        return $registryIds;
    }

    /**
     * @param  array<int, string>  $ibanUpdates  user id => IBAN
     */
    private function storeIbans(array $ibanUpdates): void
    {
        if ($ibanUpdates === []) {
            return;
        }

        EmploymentProfile::query()->whereIn('user_id', array_keys($ibanUpdates))->get()
            ->each(static fn (EmploymentProfile $profile): bool => $profile->update(['iban' => $ibanUpdates[$profile->user_id]]));
    }
}
