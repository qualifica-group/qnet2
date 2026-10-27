<?php

declare(strict_types=1);

namespace App\Stats\Opportunities;

use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\Opportunities\OpportunityVisibilityScope;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\DistributionChart;
use App\Stats\Widgets\StatFormat;
use App\Stats\Widgets\TrendChart;
use App\Stats\Widgets\Widget;
use App\Support\OperationalSiteLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Statistics panel of the `opportunities` module (spec 0040): volume, the
 * invested estimated value, the average success probability, how many were
 * generated from a Lead (BR-1), the breakdown by sede operativa (operational
 * site — it replaced the per-registry one, which averaged ~1 opportunity per
 * registry and cost a 1M-row GROUP BY) and the monthly creation trend.
 * Exactly 4 leading stat widgets, icons from the frontend's allow-list — the
 * same structural invariant every other module in this registry follows (see
 * StatsEndpointTest).
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

    /** Widgets are narrowed by OpportunityVisibilityScope, so each actor has their own numbers. */
    public function cacheScope(User $actor): string
    {
        return 'user:'.$actor->getKey();
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        $total = $this->visible()->count();

        return [
            $this->stat('total', $total, icon: 'briefcase'),
            $this->stat(
                key: 'estimated_value',
                value: round((float) $this->visible()->sum('opportunities.estimated_value'), 2),
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
                value: $this->visible()->whereNotNull('opportunities.lead_id')->count(),
                icon: 'check-circle',
            ),
            $this->distribution(
                key: 'by_operational_site',
                items: Aggregates::topRelatedLabelledBy(
                    query: $this->visible()->toBase(),
                    foreignKey: self::TABLE.'.operational_site_id',
                    limit: self::TOP_LIMIT,
                    labels: $this->operationalSiteLabels(...),
                ),
                total: $total,
                chart: DistributionChart::Bars,
            ),
            $this->trend(
                key: 'trend',
                points: Aggregates::monthlyTrend(
                    self::TABLE,
                    'created_at',
                    self::TREND_MONTHS,
                    fn ($query) => $query->whereIn('opportunities.id', $this->visible()->select('opportunities.id')),
                ),
                format: StatFormat::Number,
                chart: TrendChart::Area,
                tone: 1,
            ),
        ];
    }

    /**
     * Display labels of the ranked top-N sites only (never the whole table):
     * a site has no own name column, so the label is the one composed by
     * OperationalSiteLabel from its primary address + city, exactly as the
     * opportunity grid/detail show it (spec 0056).
     *
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function operationalSiteLabels(array $ids): array
    {
        return OperationalSite::query()
            ->with('addresses.city')
            ->whereKey($ids)
            ->get()
            ->mapWithKeys(static fn (OperationalSite $site): array => [
                $site->id => OperationalSiteLabel::compose($site->primaryAddress),
            ])
            ->all();
    }

    /**
     * The average `success_probability` among opportunities that have one,
     * rounded to 1 decimal — null (never 0) when none do, so the frontend
     * renders "—" instead of a misleading 0%.
     */
    private function averageSuccessProbability(): ?float
    {
        $average = $this->visible()->whereNotNull('opportunities.success_probability')->avg('opportunities.success_probability');

        return $average === null ? null : round((float) $average, 1);
    }

    /**
     * The actor's visible opportunities, a fresh builder per widget.
     *
     * @return Builder<Opportunity>
     */
    private function visible(): Builder
    {
        return OpportunityVisibilityScope::scopeToActor(Opportunity::query(), Auth::user());
    }
}
