<?php

declare(strict_types=1);

namespace App\Stats\Leads;

use App\Models\Lead;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\DistributionChart;
use App\Stats\Widgets\StatFormat;
use App\Stats\Widgets\TrendChart;
use App\Stats\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Statistics panel of the `leads` module (spec 0026): volume, assignment and
 * data-completeness (source/site), plus the two breakdowns the commercial
 * team works by (source, operator).
 */
class LeadsStatsDefinition extends AbstractStatsDefinition
{
    private const string TABLE = 'leads';

    public function domain(): string
    {
        return 'leads';
    }

    public function modelClass(): string
    {
        return Lead::class;
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        $total = $this->totalRows();

        return [
            $this->stat('total', $total, icon: 'users'),
            $this->stat(
                key: 'assigned',
                value: Lead::query()->whereNotNull('operator_id')->count(),
                icon: 'user-check',
            ),
            $this->stat(
                key: 'with_source',
                value: Lead::query()->whereNotNull('source_id')->count(),
                icon: 'target',
            ),
            $this->stat(
                key: 'with_site',
                value: Lead::query()->whereNotNull('operational_site_id')->count(),
                icon: 'map-pin',
            ),
            $this->distribution(
                key: 'by_source',
                items: Aggregates::topRelated(
                    query: DB::table(self::TABLE),
                    foreignKey: self::TABLE.'.source_id',
                    relatedTable: 'sources',
                    labelColumn: 'name',
                    limit: self::TOP_LIMIT,
                ),
                total: $total,
                chart: DistributionChart::Columns,
            ),
            $this->distribution(
                key: 'by_operator',
                items: Aggregates::topRelated(
                    query: DB::table(self::TABLE),
                    foreignKey: self::TABLE.'.operator_id',
                    relatedTable: 'users',
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
                chart: TrendChart::Columns,
                tone: 3,
            ),
        ];
    }
}
