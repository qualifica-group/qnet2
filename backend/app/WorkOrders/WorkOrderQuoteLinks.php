<?php

declare(strict_types=1);

namespace App\WorkOrders;

use App\Models\QuoteLine;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which offers a work order draws from and which work orders an offer feeds:
 * its own `quote_id` (mono-offer) or the offers of its lines (multi-offer,
 * spec 0267). Shared by the offer-value copy (spec 0404 part D) and the
 * synced fields (spec 0425).
 */
final class WorkOrderQuoteLinks
{
    private const string LINE_PIVOT = 'quote_line_work_order';

    /**
     * The offers a work order's fields come from: its own, or (multi-offer
     * work order) the offers of its lines, oldest first.
     *
     * @return array<int, int>
     */
    public function quoteIdsOf(WorkOrder $workOrder): array
    {
        if ($workOrder->quote_id !== null) {
            return [$workOrder->quote_id];
        }

        return QuoteLine::query()
            ->whereIn('id', DB::table(self::LINE_PIVOT)->where('work_order_id', $workOrder->id)->select('quote_line_id'))
            ->distinct()
            ->orderBy('quote_id')
            ->pluck('quote_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * Every work order fed by one of $quoteIds, mono- or multi-offer.
     *
     * @param  array<int, int>  $quoteIds
     * @return Collection<int, WorkOrder>
     */
    public function workOrdersOf(array $quoteIds): Collection
    {
        if ($quoteIds === []) {
            return new Collection;
        }

        return WorkOrder::query()
            ->where(fn (Builder $query) => $query
                ->whereIn('quote_id', $quoteIds)
                ->orWhereIn('id', DB::table(self::LINE_PIVOT)
                    ->join('quote_lines', 'quote_lines.id', '=', self::LINE_PIVOT.'.quote_line_id')
                    ->whereIn('quote_lines.quote_id', $quoteIds)
                    ->select(self::LINE_PIVOT.'.work_order_id')))
            ->orderBy('id')
            ->get();
    }
}
