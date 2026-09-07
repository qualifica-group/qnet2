<?php

declare(strict_types=1);

namespace App\Stats\OperationalSites;

use App\Models\OperationalSite;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\Widget;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Statistics panel of the `operational-sites` module (spec 0026).
 *
 * The site has no own descriptive column (spec 0011: "the site IS its
 * address"), so the geographic breakdown is aggregated on the polymorphic
 * `addresses` rows owned by the site — one per site by the module's invariant
 * (enforced by OperationalSiteService), which is why a plain COUNT(*) on the
 * address rows is a site count.
 */
class OperationalSitesStatsDefinition extends AbstractStatsDefinition
{
    private const string TABLE = 'operational_sites';

    private const string ADDRESSES_TABLE = 'addresses';

    /**
     * The staff assignment lives on the employment-profile <-> site pivot
     * (spec 0103, D-9), not on a column of either side.
     */
    private const string EMPLOYMENT_SITE_TABLE = 'employment_profile_operational_site';

    private const string LEADS_TABLE = 'leads';

    public function domain(): string
    {
        return 'operational-sites';
    }

    public function modelClass(): string
    {
        return OperationalSite::class;
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        $total = $this->totalRows();
        $morphClass = (new OperationalSite)->getMorphClass();

        return [
            $this->stat('total', $total, icon: 'map-pin'),
            $this->stat(
                key: 'with_address',
                value: Aggregates::countWithRelated(
                    table: self::TABLE,
                    relatedTable: self::ADDRESSES_TABLE,
                    foreignKey: 'addressable_id',
                    constrain: static fn (Builder $query) => $query
                        ->where(self::ADDRESSES_TABLE.'.addressable_type', $morphClass),
                ),
                icon: 'map-pin',
            ),
            // A site is "staffed" as soon as it holds ONE membership,
            // physical or remote (D-12): no `is_primary` filter. `EXISTS`
            // never duplicates the outer row even when a site has several
            // memberships, so this stays a site count, not a membership one.
            $this->stat(
                key: 'staffed',
                value: Aggregates::countWithRelated(self::TABLE, self::EMPLOYMENT_SITE_TABLE, 'operational_site_id'),
                icon: 'users',
            ),
            $this->stat(
                key: 'leads',
                value: DB::table(self::LEADS_TABLE)->whereNotNull('operational_site_id')->count(),
                icon: 'target',
            ),
            // The region IS the address' `state` (the geo reference table the
            // module already filters/sorts by — see OperationalSiteGeoColumns).
            $this->distribution(
                key: 'by_region',
                items: Aggregates::topRelated(
                    query: DB::table(self::ADDRESSES_TABLE)
                        ->where(self::ADDRESSES_TABLE.'.addressable_type', $morphClass),
                    foreignKey: self::ADDRESSES_TABLE.'.state_id',
                    relatedTable: 'states',
                    labelColumn: 'name',
                    limit: self::TOP_LIMIT,
                ),
                total: $total,
            ),
            $this->trend(
                key: 'trend',
                points: Aggregates::monthlyTrend(self::TABLE, 'created_at', self::TREND_MONTHS),
            ),
        ];
    }
}
