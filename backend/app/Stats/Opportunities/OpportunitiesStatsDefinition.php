<?php

declare(strict_types=1);

namespace App\Stats\Opportunities;

use App\Models\Opportunity;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\DistributionChart;
use App\Stats\Widgets\StatFormat;
use App\Stats\Widgets\TrendChart;
use App\Stats\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Statistics panel of the `opportunities` module (spec 0040): volume, the
 * invested estimated value, the average success probability, how many were
 * generated from a Lead (BR-1), the breakdown by anagrafica (registry) and
 * the monthly creation trend. Exactly 4 leading stat widgets, icons from the
 * frontend's allow-list — the same structural invariant every other module
 * in this registry follows (see StatsEndpointTest).
 */
class OpportunitiesStatsDefinition extends AbstractStatsDefinition
{
    private const string TABLE = 'opportunities';

    public function domain(): string
    {
        return 'opportunities';
    }

    public function modelClass(): string
    {
        return Opportunity::class;
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        $total = $this->totalRows();

        return [
            $this->stat('total', $total, icon: 'briefcase'),
            $this->stat(
                key: 'estimated_value',
                value: round((float) DB::table(self::TABLE)->sum('estimated_value'), 2),
                format: StatFormat::Currency,
                icon: 'wallet',
            ),
            $this->stat(
                key: 'average_probability',
                value: $this->averageSuccessProbability(),
                format: StatFormat::Percent,
                icon: 'percent',
            ),
            $this->stat(
                key: 'from_lead',
                value: Opportunity::query()->whereNotNull('lead_id')->count(),
                icon: 'check-circle',
            ),
            $this->distribution(
                key: 'by_registry',
                items: Aggregates::topRelated(
                    query: DB::table(self::TABLE),
                    foreignKey: self::TABLE.'.registry_id',
                    relatedTable: 'registries',
                    labelColumn: 'name',
                    limit: self::TOP_LIMIT,
                ),
                total: $total,
                chart: DistributionChart::Bars,
            ),
            $this->trend(
                key: 'trend',
                points: Aggregates::monthlyTrend(self::TABLE, 'created_at', self::TREND_MONTHS),
                format: StatFormat::Number,
                chart: TrendChart::Area,
                tone: 1,
            ),
        ];
    }

    /**
     * The average `success_probability` among opportunities that have one,
     * rounded to 1 decimal — null (never 0) when none do, so the frontend
     * renders "—" instead of a misleading 0%.
     */
    private function averageSuccessProbability(): ?float
    {
        $average = DB::table(self::TABLE)->whereNotNull('success_probability')->avg('success_probability');

        return $average === null ? null : round((float) $average, 1);
    }
}
