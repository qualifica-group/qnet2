<?php

declare(strict_types=1);

namespace App\Stats\Projects;

use App\Models\Project;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\DistributionChart;
use App\Stats\Widgets\StatFormat;
use App\Stats\Widgets\TrendChart;
use App\Stats\Widgets\Widget;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Statistics panel of the `projects` module (spec 0026): volume, the
 * campaigns and leads a project generates, and the budget allocated to
 * project-linked campaigns.
 *
 * The `leads` KPI keeps the SAME semantics as ProjectService::summary()
 * (GET /projects/summary, spec 0023 BR-1) — a project has no own leads, only
 * the ones reachable through its campaigns (`campaigns.project_id NOT NULL`) —
 * so the panel and the legacy tiles can never disagree. The counting is
 * replicated here as aggregate queries rather than reusing the service, which
 * stays untouched.
 */
class ProjectsStatsDefinition extends AbstractStatsDefinition
{
    private const string TABLE = 'projects';

    private const string CAMPAIGNS_TABLE = 'campaigns';

    private const string LEADS_TABLE = 'leads';

    public function domain(): string
    {
        return 'projects';
    }

    public function modelClass(): string
    {
        return Project::class;
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        $total = $this->totalRows();
        $leads = $this->projectLeadsQuery()->count();
        $allocatedBudget = (float) DB::table(self::CAMPAIGNS_TABLE)->whereNotNull('project_id')->sum('total_budget');

        return [
            $this->stat('total', $total, icon: 'layers'),
            $this->stat(
                key: 'campaigns',
                value: DB::table(self::CAMPAIGNS_TABLE)->whereNotNull('project_id')->count(),
                icon: 'megaphone',
            ),
            $this->stat('leads', $leads, icon: 'users'),
            $this->stat(
                key: 'allocated_budget',
                value: round($allocatedBudget, 2),
                format: StatFormat::Currency,
                icon: 'wallet',
            ),
            // `pipeline_statuses` is a lookup table, not an enum: group on the
            // relation and take its own name/color as the item's presentation.
            $this->distribution(
                key: 'by_status',
                items: Aggregates::topRelated(
                    query: DB::table(self::TABLE),
                    foreignKey: self::TABLE.'.pipeline_status_id',
                    relatedTable: 'pipeline_statuses',
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
                chart: TrendChart::Columns,
                tone: 5,
            ),
        ];
    }

    /**
     * The leads reachable through a project (no direct FK: only through a
     * campaign that belongs to a project). A join, not a whereHas subquery, so
     * the count stays a single aggregate.
     */
    private function projectLeadsQuery(): Builder
    {
        return DB::table(self::LEADS_TABLE)
            ->join(self::CAMPAIGNS_TABLE, self::CAMPAIGNS_TABLE.'.id', '=', self::LEADS_TABLE.'.campaign_id')
            ->whereNotNull(self::CAMPAIGNS_TABLE.'.project_id');
    }
}
