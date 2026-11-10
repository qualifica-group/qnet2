<?php

declare(strict_types=1);

namespace App\Tables\CategoryTabs;

use Closure;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The (work order, source offer) pairs behind the Commesse category filters and
 * visibility scope (spec 0335 rev.3, D-9): a commessa's own `quote_id` (mono-offer)
 * plus the offers of its lines in `quote_line_work_order`, the only source of a
 * multi-offer one (spec 0267, D-4). Same set as `WorkOrder::sourceQuoteIds()`, as SQL.
 */
final class WorkOrderSourceQuotes
{
    /**
     * Columns `work_order_id`, `quote_id`; UNION drops the duplicate pairs.
     */
    public static function query(): QueryBuilder
    {
        $lineQuotes = DB::table('quote_line_work_order')
            ->join('quote_lines', 'quote_lines.id', '=', 'quote_line_work_order.quote_line_id')
            ->select('quote_line_work_order.work_order_id', 'quote_lines.quote_id');

        return DB::table('work_orders')
            ->whereNotNull('work_orders.quote_id')
            ->select('work_orders.id as work_order_id', 'work_orders.quote_id')
            ->union($lineQuotes);
    }

    /**
     * A `whereIn` subquery callback selecting the work orders with a source
     * offer carrying a REVENUE line in the given category (or branch).
     *
     * @param  int|array<int, int>  $categoryIds
     * @return Closure(QueryBuilder): QueryBuilder
     */
    public static function withRevenueCategory(int|array $categoryIds): Closure
    {
        return static fn (QueryBuilder $pairs): QueryBuilder => $pairs
            ->select('source_quotes.work_order_id')
            ->fromSub(self::query(), 'source_quotes')
            ->whereIn('source_quotes.quote_id', RevenueCategoryQuoteIds::subquery($categoryIds));
    }
}
