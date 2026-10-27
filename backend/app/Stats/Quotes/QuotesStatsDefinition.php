<?php

declare(strict_types=1);

namespace App\Stats\Quotes;

use App\Enums\WorkflowStatusGroup;
use App\Models\Quote;
use App\Models\User;
use App\Services\Quotes\QuoteVisibilityScope;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\DistributionChart;
use App\Stats\Widgets\StatFormat;
use App\Stats\Widgets\TrendChart;
use App\Stats\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Statistics panel of the `quotes` module (spec 0151, D-8): volume, net
 * revenue, net margin and how many closed won, the breakdown by workflow
 * status (colour of the status row) and the monthly creation trend. Narrowed
 * to the quotes the actor may see (QuoteVisibilityScope, spec 0316), so each
 * actor has their own numbers and cache entry.
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

    /** Widgets are narrowed by QuoteVisibilityScope, so each actor has their own numbers. */
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
                key: 'revenue_net',
                value: round((float) $this->visible()->sum('quotes.revenue_net'), 2),
                format: StatFormat::Currency,
                icon: 'wallet',
            ),
            $this->stat(
                key: 'margin_net',
                value: round((float) $this->visible()->sum('quotes.margin_net'), 2),
                format: StatFormat::Currency,
                icon: 'trending-up',
            ),
            $this->stat('won', $this->wonCount(), icon: 'check-circle'),
            $this->distribution(
                key: 'by_status',
                items: Aggregates::topRelated(
                    query: $this->visible()->toBase(),
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
                points: Aggregates::monthlyTrend(
                    self::TABLE,
                    'created_at',
                    self::TREND_MONTHS,
                    fn ($query) => $query->whereIn('quotes.id', $this->visible()->select('quotes.id')),
                ),
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
        return $this->visible()
            ->join(self::STATUS_TABLE, self::STATUS_TABLE.'.id', '=', self::TABLE.'.quote_workflow_status_id')
            ->where(self::STATUS_TABLE.'.group', WorkflowStatusGroup::ClosedWon->value)
            ->count();
    }

    /**
     * The actor's visible quotes, a fresh builder per widget.
     *
     * @return Builder<Quote>
     */
    private function visible(): Builder
    {
        return QuoteVisibilityScope::scopeToActor(Quote::query(), Auth::user());
    }
}
