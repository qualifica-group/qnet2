<?php

declare(strict_types=1);

namespace App\Stats\Companies;

use App\Models\Company;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\TrendChart;
use App\Stats\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Statistics panel of the `companies` module (spec 0026): volume, data
 * completeness (VAT number — the module's main data-quality signal) and the
 * company sites footprint (`company_sites.company_id`, a nullable link).
 */
class CompaniesStatsDefinition extends AbstractStatsDefinition
{
    private const string TABLE = 'companies';

    private const string SITES_TABLE = 'company_sites';

    public function domain(): string
    {
        return 'companies';
    }

    public function modelClass(): string
    {
        return Company::class;
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        $total = $this->totalRows();

        return [
            $this->stat('total', $total, icon: 'building'),
            $this->percentStat(
                key: 'with_vat_number',
                count: Company::query()->whereNotNull('vat_number')->count(),
                total: $total,
                icon: 'percent',
            ),
            $this->stat(
                key: 'with_sites',
                value: Aggregates::countWithRelated(self::TABLE, self::SITES_TABLE, 'company_id'),
                icon: 'building',
            ),
            $this->stat(
                key: 'sites',
                value: DB::table(self::SITES_TABLE)->count(),
                icon: 'map-pin',
            ),
            $this->trend(
                key: 'trend',
                points: Aggregates::monthlyTrend(self::TABLE, 'created_at', self::TREND_MONTHS),
                chart: TrendChart::Line,
                tone: 1,
            ),
        ];
    }
}
