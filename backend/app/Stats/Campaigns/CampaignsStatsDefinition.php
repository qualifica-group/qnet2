<?php

declare(strict_types=1);

namespace App\Stats\Campaigns;

use App\Models\Campaign;
use App\Models\Lead;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\DistributionChart;
use App\Stats\Widgets\StatFormat;
use App\Stats\Widgets\TrendChart;
use App\Stats\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Statistics panel of the `campaigns` module (spec 0026): volume, the share
 * linked to a project, the invested budget and the generated leads.
 */
class CampaignsStatsDefinition extends AbstractStatsDefinition
{
    private const string TABLE = 'campaigns';

    /**
     * The campaign's EFFECTIVE project status: a project-linked campaign nulls
     * its own `pipeline_status_id` and derives it from the project (spec 0023,
     * BR-2), so grouping on the raw column alone would drop every linked
     * campaign from the breakdown. Static SQL built from column names only —
     * no request input reaches it.
     */
    private const string EFFECTIVE_STATUS_ID = 'COALESCE(campaigns.pipeline_status_id, projects.pipeline_status_id)';

    public function domain(): string
    {
        return 'campaigns';
    }

    public function modelClass(): string
    {
        return Campaign::class;
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        $total = $this->totalRows();

        return [
            $this->stat('total', $total, icon: 'megaphone'),
            $this->stat(
                key: 'linked_to_project',
                value: Campaign::query()->whereNotNull('project_id')->count(),
                icon: 'layers',
            ),
            $this->stat(
                key: 'total_budget',
                value: round((float) DB::table(self::TABLE)->sum('total_budget'), 2),
                format: StatFormat::Currency,
                icon: 'wallet',
            ),
            // Every lead belongs to a campaign (leads.campaign_id is mandatory,
            // spec 0024 BR-1), so the campaign-generated leads ARE all leads.
            $this->stat('generated_leads', Lead::query()->count(), icon: 'users'),
            $this->distribution(
                key: 'by_pipeline_status',
                items: Aggregates::topRelated(
                    query: DB::table(self::TABLE)
                        ->leftJoin('projects', 'projects.id', '=', self::TABLE.'.project_id'),
                    foreignKey: DB::raw(self::EFFECTIVE_STATUS_ID),
                    relatedTable: 'pipeline_statuses',
                    labelColumn: 'name',
                    limit: self::TOP_LIMIT,
                    colorColumn: 'color',
                ),
                total: $total,
                chart: DistributionChart::Stacked,
            ),
            $this->trend(
                key: 'trend',
                points: Aggregates::monthlyTrend(self::TABLE, 'created_at', self::TREND_MONTHS),
                chart: TrendChart::Line,
                tone: 3,
            ),
        ];
    }
}
