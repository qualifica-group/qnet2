<?php

namespace App\Tables\Users;

use App\Models\Address;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;

/**
 * The `operational_site` grid column on `users` (spec 0015, revised by spec
 * 0103 D-7): an employment profile no longer has a single site, it holds any
 * number of memberships via the `employment_profile_operational_site` pivot
 * — at most one PHYSICAL (`is_primary` true, EmploymentProfile::
 * primaryOperationalSite()) plus zero or more REMOTE ones
 * (EmploymentProfile::operationalSites()). The 3 behaviours intentionally
 * diverge on that split:
 *
 *  - CELL: the PHYSICAL site only (AC-017) — empty when the user has none,
 *    even if they hold remote memberships.
 *  - FILTER (free text): matches ANY membership, physical or remote
 *    (AC-018) — a user is findable by a remote site's address even though
 *    the cell only ever shows their physical one.
 *  - SORT: the PHYSICAL site's primary address `line1` (AC-019), via a
 *    subquery correlated to `employment_profiles.user_id` and joined
 *    through the pivot — never a row-multiplying JOIN on the main query.
 *    A user with no physical site sorts NULL, and both MySQL and SQLite
 *    (dev/test, CLAUDE.md §0 — the only two engines this project runs on)
 *    place a subquery NULL FIRST in ASC / LAST in DESC identically, so the
 *    outcome is deterministic across both, not left to per-engine chance.
 *
 * Extracted out of UserEmploymentColumns (file-size split, engineering.md
 * §6): that file was already past the 300-line soft limit, and this
 * column's 3 behaviours diverging on physical-vs-remote earned their own
 * unit. Mirrors Tables/Shared/OperationalSiteColumn's single-FK convention,
 * adapted for the many-to-many pivot.
 */
final class UserOperationalSiteColumn
{
    public function __construct(private readonly FilterApplier $filterApplier) {}

    /**
     * Format the user's PHYSICAL site's primary address as "line1[- city]",
     * or null when they have none (AC-017 — never falls back to a remote
     * membership). Reads entirely off the eager-loaded
     * `employment.primaryOperationalSite` relation
     * (UsersTableDefinition::baseQuery) — never queries.
     */
    public function label(?EmploymentProfile $employment): ?string
    {
        $address = $employment?->primaryOperationalSite->first()?->primaryAddress;

        if ($address === null) {
            return null;
        }

        $city = $address->city?->localizedName();

        return $city !== null ? "{$address->line1} - {$city}" : $address->line1;
    }

    /**
     * CONDITIONS-ONLY text filter (mirrors UserPersonalDataColumns::
     * applyAddressFilter — no Set/checklist, spec 0005 UX decision, hence
     * `hasFilterValues:false` on the column): bound LIKE on ANY membership's
     * primary address street/postal/city-name, physical or remote (AC-018).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, array $filter): void
    {
        $value = $filter['filter'] ?? null;

        if (! is_scalar($value) || $value === '') {
            return;
        }

        $needle = '%'.$this->filterApplier->escapeLike((string) $value).'%';

        $query->whereHas('employment.operationalSites.addresses', static function (Builder $addressQuery) use ($needle): void {
            $addressQuery->where('is_primary', true)
                ->where(function (Builder $match) use ($needle): void {
                    $match->where('line1', 'like', $needle)
                        ->orWhere('postal_code', 'like', $needle)
                        ->orWhereHas('city', static function (Builder $cityQuery) use ($needle): void {
                            $cityQuery->where('name', 'like', $needle);
                        });
                });
        });
    }

    /**
     * ORDER BY the PHYSICAL membership's site's primary address `line1`,
     * via a subquery correlated to `employment_profiles.user_id` and joined
     * through the pivot, scoped to `is_primary=true` so a remote membership
     * never gets picked instead (AC-019).
     *
     * @return Builder<Model>
     */
    public function sortSubquery(): Builder
    {
        return Address::query()
            ->select('addresses.line1')
            ->join('employment_profile_operational_site', function (JoinClause $join): void {
                $join->on('employment_profile_operational_site.operational_site_id', '=', 'addresses.addressable_id')
                    ->where('employment_profile_operational_site.is_primary', true);
            })
            ->join('employment_profiles', 'employment_profiles.id', '=', 'employment_profile_operational_site.employment_profile_id')
            ->where('addresses.addressable_type', (new OperationalSite)->getMorphClass())
            ->where('addresses.is_primary', true)
            ->whereColumn('employment_profiles.user_id', 'users.id')
            ->limit(1);
    }
}
