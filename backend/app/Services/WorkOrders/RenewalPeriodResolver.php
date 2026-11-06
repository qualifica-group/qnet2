<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

/**
 * The single place that decides the contract renewal period of a quote line
 * (spec 0358, D-4): the pivot `quote_line_work_order.renewal_number`.
 */
final class RenewalPeriodResolver
{
    /**
     * Current period of the contract of each offer (0 when the offer has no
     * contract or was never renewed). One query.
     *
     * @param  array<int, int>  $quoteIds
     * @return array<int, int> quote id => period
     */
    public function periodsForQuotes(array $quoteIds): array
    {
        $periods = array_fill_keys($quoteIds, 0);

        if ($quoteIds === []) {
            return $periods;
        }

        $rows = DB::table('contracts')
            ->join('contract_renewals', 'contract_renewals.contract_id', '=', 'contracts.id')
            ->whereIn('contracts.quote_id', $quoteIds)
            ->groupBy('contracts.quote_id')
            ->selectRaw('contracts.quote_id, MAX(contract_renewals.number) as period')
            ->get();

        foreach ($rows as $row) {
            $periods[(int) $row->quote_id] = (int) $row->period;
        }

        return $periods;
    }

    /**
     * Period a line of each offer takes when it enters `$workOrder`: the
     * period of the lines of the SAME offer the commessa already holds,
     * otherwise the contract's current one.
     *
     * @param  array<int, int>  $quoteIds
     * @return array<int, int> quote id => period
     */
    public function periodForWorkOrderLines(WorkOrder $workOrder, array $quoteIds): array
    {
        $periods = $this->periodsForQuotes($quoteIds);

        if (! $workOrder->exists) {
            return $periods;
        }

        $held = DB::table('quote_line_work_order')
            ->join('quote_lines', 'quote_lines.id', '=', 'quote_line_work_order.quote_line_id')
            ->where('quote_line_work_order.work_order_id', $workOrder->id)
            ->whereIn('quote_lines.quote_id', $quoteIds)
            ->groupBy('quote_lines.quote_id')
            ->selectRaw('quote_lines.quote_id, MAX(quote_line_work_order.renewal_number) as period')
            ->get();

        foreach ($held as $row) {
            $periods[(int) $row->quote_id] = (int) $row->period;
        }

        return $periods;
    }
}
