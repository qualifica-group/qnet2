<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering;

use App\Models\CompanySite;
use App\Models\CompanySiteBank;
use App\Models\Quote;
use App\Support\AddressLabel;
use App\Support\OperationalSiteLabel;

/**
 * Resolves the `company`, `company_site` and `operational_site` variable
 * categories (spec 0069's frozen catalogue) — the issuing-entity categories,
 * none of which carry PII (D-6 only ever masks `client.*`), split out of
 * VariableResolver to keep it under the file-size soft limit
 * (engineering.md §6).
 */
final class OrganizationFieldResolver
{
    public function company(string $key, Quote $quote): ?string
    {
        $company = $quote->company;
        $address = $company?->primaryAddress;

        return match ($key) {
            'denomination' => ValueFormatter::text($company?->denomination),
            'vat_number' => ValueFormatter::text($company?->vat_number),
            'address' => AddressLabel::singleLine($address),
            'address_city' => ValueFormatter::text($address?->city?->localizedName()),
            'address_postal_code' => ValueFormatter::text($address?->postal_code),
            default => null,
        };
    }

    public function companySite(string $key, Quote $quote): ?string
    {
        $site = $quote->companySite;
        $address = $site === null ? null : PrimaryAddressPicker::pick($site->personalData?->addresses ?? collect());
        $bank = $this->primaryBank($site);

        return match ($key) {
            'name' => ValueFormatter::text($site?->name),
            'bank_name' => ValueFormatter::text($bank?->name),
            'bank_iban' => ValueFormatter::text($bank?->iban),
            'address' => AddressLabel::singleLine($address),
            'address_city' => ValueFormatter::text($address?->city?->localizedName()),
            'address_postal_code' => ValueFormatter::text($address?->postal_code),
            default => null,
        };
    }

    public function operationalSite(string $key, Quote $quote): ?string
    {
        return match ($key) {
            'label' => OperationalSiteLabel::compose($quote->operationalSite?->primaryAddress),
            default => null,
        };
    }

    private function primaryBank(?CompanySite $site): ?CompanySiteBank
    {
        if ($site === null || ! $site->relationLoaded('banks')) {
            return null;
        }

        return $site->banks->firstWhere('is_primary', true) ?? $site->banks->first();
    }
}
