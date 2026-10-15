<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Models\Company;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Conversions shared by the two ledger sources: decimal strings to integer
 * cents, dates to immutable days, related records to `{id, name}` labels.
 */
trait BuildsLedgerEntries
{
    private function cents(mixed $amount): int
    {
        return (int) bcmul((string) $amount, '100', 0);
    }

    private function day(?CarbonInterface $date): ?CarbonImmutable
    {
        return $date === null ? null : CarbonImmutable::parse($date->toDateString());
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function companyLabel(?Company $company): ?array
    {
        return $company === null ? null : ['id' => $company->id, 'name' => (string) $company->denomination];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function companySiteLabel(?CompanySite $site): ?array
    {
        return $site === null ? null : ['id' => $site->id, 'name' => (string) $site->name];
    }

    /**
     * The alias, falling back to the id as a string (as the installments grid does).
     *
     * @return array{id: int, name: string}|null
     */
    private function operationalSiteLabel(?OperationalSite $site): ?array
    {
        return $site === null ? null : ['id' => $site->id, 'name' => (string) ($site->alias ?? $site->id)];
    }
}
