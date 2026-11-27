<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\QuoteLineType;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;

/**
 * The offer's budget of a commessa (spec 0190 D-1): the COST lines of its
 * source offer(s) (all of them on a multi-offer commessa, spec 0267 D-10)
 * allocated to one of ITS revenue lines, plus the generic ones, in the
 * offer's order. Shared by the cost overview and the sync from the offer
 * (spec 0436 D-1).
 */
final class WorkOrderBudgetLines
{
    /**
     * @param  array<int, string>  $relations  eager loads of the budget lines
     * @return Collection<int, QuoteLine>
     */
    public function for(WorkOrder $workOrder, array $relations = []): Collection
    {
        $revenueIds = $workOrder->quoteLines()->pluck('quote_lines.id')->map(static fn (mixed $id): int => (int) $id)->all();

        return QuoteLine::query()
            ->whereIn('quote_id', $workOrder->isMultiQuote() ? $workOrder->sourceQuoteIds() : [$workOrder->quote_id])
            ->where('line_type', QuoteLineType::Cost)
            ->where(static fn ($query) => $query->whereNull('offer_line_id')->orWhereIn('offer_line_id', $revenueIds))
            ->with($relations)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
