<?php

declare(strict_types=1);

namespace App\Services\ManagementControl;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The reference offer of a commessa (spec 0268, D-10; shared with spec 0271): its
 * own, or the lowest id among the offers of its assigned lines on a multi-offer
 * one. Company, sites and commercial of a commessa are those of this offer
 * (or its own company and sites, spec 0431: see COMPANY_ID...).
 */
final class WorkOrderReferenceQuote
{
    /** Constant SQL text, no input in it. */
    public const string EXPRESSION = 'COALESCE(work_orders.quote_id, (SELECT MIN(quote_lines.quote_id) FROM quote_line_work_order INNER JOIN quote_lines ON quote_lines.id = quote_line_work_order.quote_line_id WHERE quote_line_work_order.work_order_id = work_orders.id))';

    /**
     * Spec 0431 (D-12): the company and sites of a commessa, its own when it was
     * born without an offer (then it has no reference offer), the reference
     * offer's otherwise. Only one side is ever set. Constant SQL text.
     */
    public const string COMPANY_ID = 'COALESCE(work_orders.company_id, quotes.company_id)';

    public const string COMPANY_SITE_ID = 'COALESCE(work_orders.company_site_id, quotes.company_site_id)';

    public const string OPERATIONAL_SITE_ID = 'COALESCE(work_orders.operational_site_id, quotes.operational_site_id)';

    /**
     * Left-joins `quotes` (the reference offer) to a query on `work_orders`.
     */
    public static function join(Builder $workOrders): Builder
    {
        return $workOrders->leftJoin('quotes', 'quotes.id', '=', DB::raw(self::EXPRESSION));
    }
}
