<?php

declare(strict_types=1);

namespace App\Stats\LeadImports;

use App\Enums\ImportStatus;
use App\Models\ImportRun;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\DistributionChart;
use App\Stats\Widgets\StatFormat;
use App\Stats\Widgets\TrendChart;
use App\Stats\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Statistics panel of the `import-runs` module (spec 0034): volume and
 * outcome of every lead import run. Every widget is scoped to
 * `resource='leads'` — mirroring LeadImportsTableDefinition's baseQuery; runs
 * are no longer owner-scoped (user decision 2026-09-16).
 *
 * Exactly 4 leading stat widgets (total/completed/failed/rows_imported), same
 * as every other module's panel (StatsEndpointTest's cross-module invariant:
 * the frontend renders a fixed 4-column stat grid). `rows_modified`/
 * `rows_skipped` — present in spec 0034's descriptive widget list — are
 * intentionally NOT separate stat tiles here to respect that invariant; the
 * per-run breakdown (imported/modified/skipped/error counts) already lives on
 * the run's own detail page via ImportRunResource's counters, not this
 * aggregate panel.
 */
class LeadImportsStatsDefinition extends AbstractStatsDefinition
{
    /** The `import_runs.resource` key this panel is scoped to. */
    private const RESOURCE = 'leads';

    public function domain(): string
    {
        return 'import-runs';
    }

    public function modelClass(): string
    {
        return ImportRun::class;
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        $total = $this->leadsRunsQuery()->count();

        return [
            $this->stat('total', $total, icon: 'layers'),
            $this->stat(
                key: 'completed',
                value: $this->leadsRunsQuery()->where('status', ImportStatus::Completed)->count(),
                icon: 'check-circle',
            ),
            $this->stat(
                key: 'failed',
                value: $this->leadsRunsQuery()->where('status', ImportStatus::Failed)->count(),
            ),
            $this->stat(
                key: 'rows_imported',
                value: (int) $this->leadsRunsQuery()->sum('imported_rows'),
                icon: 'package',
            ),
            $this->distribution(
                key: 'by_status',
                items: Aggregates::byEnumColumn(
                    table: 'import_runs',
                    column: 'status',
                    enum: ImportStatus::class,
                    constrain: $this->scopeToLeadsRuns(...),
                ),
                total: $total,
                chart: DistributionChart::Stacked,
            ),
            $this->trend(
                key: 'trend',
                points: Aggregates::monthlyTrend(
                    table: 'import_runs',
                    column: 'created_at',
                    months: self::TREND_MONTHS,
                    constrain: $this->scopeToLeadsRuns(...),
                ),
                format: StatFormat::Number,
                chart: TrendChart::Columns,
                tone: 1,
            ),
        ];
    }

    /**
     * @return EloquentBuilder<ImportRun>
     */
    private function leadsRunsQuery(): EloquentBuilder
    {
        return ImportRun::query()->where('resource', self::RESOURCE);
    }

    /**
     * The same resource scope as leadsRunsQuery(), applied to a raw query
     * builder (Aggregates' helpers operate on `DB::table()`, not Eloquent).
     */
    private function scopeToLeadsRuns(QueryBuilder $query): void
    {
        $query->where('resource', self::RESOURCE);
    }
}
