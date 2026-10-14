<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Address;
use App\Models\OperationalSite;

/**
 * Shared composition of an OperationalSite's DISPLAY label (spec 0056 audit):
 * the site has no own name column (`id`/`old_id`/`alias`/timestamps only —
 * OperationalSite::class docblock), so its identity IS its primary address,
 * shown as "{line1} - {city}" (just `line1` when the address has no city).
 * Relies on the caller having eager-loaded `addresses.city` — this never
 * queries.
 *
 * This composition was duplicated byte-identical across 14 call sites
 * (OperationalSiteForSelectResource, LeadResource, ProjectResource/
 * ProjectForSelectResource, CampaignResource/CampaignForSelectResource,
 * ProjectsTableDefinition/CampaignsTableDefinition, LeadOperationalSiteColumn,
 * BusinessFunctionOperationalSitesColumn, UserForSelectResource,
 * ImportRunRowResource, EmploymentResource, UserEmploymentColumns) with no
 * shared helper. Per spec 0056's blast-radius constraint those 14 copies are
 * NOT refactored here (follow-up); only new code (Opportunity + Gestione
 * Richieste) consumes this helper, so the duplication count stops growing
 * without touching working modules.
 *
 * LATENT BUG NOT PROPAGATED HERE (spec 0056 audit): every one of those 14
 * copies reads `$site->addresses->first()`, NOT filtered on `is_primary`,
 * while the filter/sort/distinct path (LeadOperationalSiteColumn and this
 * spec's own OperationalSiteColumn) all match on `where('is_primary', true)`.
 * On a site with multiple addresses whose first is not the primary one, the
 * displayed label and the filter/sort key would DIVERGE. This helper uses
 * `OperationalSite::$primaryAddress` (already falls back to `first()` when
 * no row is flagged primary) instead, so new code never inherits that
 * mismatch — the 14 existing copies are left as-is (out of scope, flagged as
 * a real defect for a follow-up, not "fixed" here).
 */
final class OperationalSiteLabel
{
    public static function compose(?Address $address): string
    {
        if ($address === null) {
            return '';
        }

        $city = $address->city?->localizedName();

        return $city === null ? (string) $address->line1 : "{$address->line1} - {$city}";
    }

    /** The site's own alias when set, otherwise its address label (needs `addresses.city` eager-loaded). */
    public static function displayName(OperationalSite $site): string
    {
        return filled($site->alias) ? (string) $site->alias : self::compose($site->primaryAddress);
    }

    /**
     * @return array{id: int, label: string}|null
     */
    public static function summarize(?OperationalSite $site): ?array
    {
        if ($site === null) {
            return null;
        }

        return ['id' => $site->id, 'label' => self::compose($site->primaryAddress)];
    }
}
