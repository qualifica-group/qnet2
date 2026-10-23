<?php

declare(strict_types=1);

namespace App\Services\Commissions;

use App\Enums\CommissionOrigin;
use App\Enums\QuoteLineType;
use App\Enums\WorkflowStatusGroup;
use App\Models\CommissionConfiguration;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteLineCommission;
use Illuminate\Database\Eloquent\Builder;

/**
 * What an explicit recalculation would touch (spec 0308, D-3/D-6): the
 * numbers behind the controlled-choice dialogs and the line selection of the
 * rule-driven job. Closed quotes never count.
 */
final class CommissionRecalculationImpact
{
    private const array CLOSED_GROUPS = [WorkflowStatusGroup::ClosedWon, WorkflowStatusGroup::ClosedLost];

    public function isClosed(Quote $quote): bool
    {
        return in_array($quote->quoteWorkflowStatus?->group, self::CLOSED_GROUPS, true);
    }

    /** @return array{lines_count: int, commissions_count: int, manual_commissions_count: int, closed: bool} */
    public function forQuote(Quote $quote): array
    {
        $lineIds = $quote->offerLines()->whereNotNull('product_id')->pluck('id');
        $commissions = QuoteLineCommission::query()->whereIn('quote_line_id', $lineIds);

        return [
            'lines_count' => $lineIds->count(),
            'commissions_count' => (clone $commissions)->count(),
            'manual_commissions_count' => (clone $commissions)->where('origin', CommissionOrigin::ManualOverride)->count(),
            'closed' => $this->isClosed($quote),
        ];
    }

    /** @return array{quotes_count: int, lines_count: int, manual_commissions_count: int} */
    public function forConfiguration(CommissionConfiguration $configuration): array
    {
        $lines = $this->touchedLines($configuration)->get(['id', 'quote_id']);

        return [
            'quotes_count' => $lines->pluck('quote_id')->unique()->count(),
            'lines_count' => $lines->count(),
            'manual_commissions_count' => QuoteLineCommission::query()
                ->whereIn('quote_line_id', $lines->pluck('id'))
                ->where('origin', CommissionOrigin::ManualOverride)
                ->count(),
        ];
    }

    /**
     * The touched line ids grouped by quote id.
     *
     * @return array<int, array<int, int>>
     */
    public function touchedLineIdsByQuote(CommissionConfiguration $configuration): array
    {
        return $this->touchedLines($configuration)->get(['id', 'quote_id'])
            ->groupBy('quote_id')
            ->map(fn ($lines): array => $lines->pluck('id')->all())
            ->all();
    }

    /**
     * REVENUE lines of non-closed quotes carrying at least one rule-driven
     * commission of $configuration.
     *
     * @return Builder<QuoteLine>
     */
    private function touchedLines(CommissionConfiguration $configuration): Builder
    {
        return QuoteLine::query()
            ->where('line_type', QuoteLineType::Revenue)
            ->whereNotNull('product_id')
            ->whereHas('quote.quoteWorkflowStatus', fn (Builder $status) => $status->whereNotIn('group', self::CLOSED_GROUPS))
            ->whereHas('commissions', fn (Builder $commissions) => $commissions
                ->where('commission_configuration_id', $configuration->id)
                ->where('origin', '!=', CommissionOrigin::ManualOverride));
    }
}
