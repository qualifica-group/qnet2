<?php

declare(strict_types=1);

namespace App\Stats\Quotes;

use App\Enums\WorkflowStatusGroup;
use App\Models\Quote;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\DistributionChart;
use App\Stats\Widgets\StatFormat;
use App\Stats\Widgets\TrendChart;
use App\Stats\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Statistics panel of the `quotes` module (spec 0151, D-8): volume, net
 * revenue, net margin and how many closed won, the breakdown by workflow
 * status (colour of the status row) and the monthly creation trend. Global,
 * not narrowed to the actor (like Opportunities): `QuotesTableDefinition`
 * does not restrict visibility either.
 */
class QuotesStatsDefinition extends AbstractStatsDefinition
{
    private const string TABLE = 'quotes';

    private const string STATUS_TABLE = 'quote_workflow_statuses';

    public function domain(): string
    {
        return 'quotes';
    }

    public function modelClass(): string
    {
        return Quote::class;
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
                key: 'revenue_net',
                value: round((float) DB::table(self::TABLE)->sum('revenue_net'), 2),
                format: StatFormat::Currency,
                icon: 'wallet',
            ),
            $this->stat(
                key: 'margin_net',
                value: round((float) DB::table(self::TABLE)->sum('margin_net'), 2),
                format: StatFormat::Currency,
                icon: 'trending-up',
            ),
            $this->stat('won', $this->wonCount(), icon: 'check-circle'),
            $this->distribution(
                key: 'by_status',
                items: Aggregates::topRelated(
                    query: DB::table(self::TABLE),
                    foreignKey: self::TABLE.'.quote_workflow_status_id',
                    relatedTable: self::STATUS_TABLE,
                    labelColumn: 'name',
                    limit: self::TOP_LIMIT,
                    colorColumn: 'color',
                ),
                total: $total,
                chart: DistributionChart::Donut,
            ),
            $this->trend(
                key: 'trend',
                points: Aggregates::monthlyTrend(self::TABLE, 'created_at', self::TREND_MONTHS),
                chart: TrendChart::Line,
                tone: 4,
            ),
        ];
    }

    /**
     * How many quotes sit on a status whose phase is `closed_won` (D-8).
     */
    private function wonCount(): int
    {
        return DB::table(self::TABLE)
            ->join(self::STATUS_TABLE, self::STATUS_TABLE.'.id', '=', self::TABLE.'.quote_workflow_status_id')
            ->where(self::STATUS_TABLE.'.group', WorkflowStatusGroup::ClosedWon->value)
            ->count();
    }
}
