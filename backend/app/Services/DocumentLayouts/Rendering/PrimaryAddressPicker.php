<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering;

use App\Models\Address;
use Illuminate\Support\Collection;

/**
 * Picks the address a `client`/`company`/`company_site` variable renders from
 * an owner's `addresses` collection: the primary one, falling back to the
 * first — the exact same rule `Company::primaryAddress()` and
 * `OperationalSite::primaryAddress()` already apply, repeated here because
 * `Registry`/`PersonalData`/`CompanySite` have no such accessor of their own.
 * Never queries: the caller must have eager-loaded the collection.
 *
 * @internal Rendering-only helper — NOT a replacement for
 * `App\Support\AddressLabel`/`OperationalSiteLabel` (0069, D-5), which this
 * class's callers still use to format the picked address into display text.
 */
final class PrimaryAddressPicker
{
    private function __construct() {}

    /**
     * @param  Collection<int, Address>  $addresses
     */
    public static function pick(Collection $addresses): ?Address
    {
        return $addresses->firstWhere('is_primary', true) ?? $addresses->first();
    }
}
