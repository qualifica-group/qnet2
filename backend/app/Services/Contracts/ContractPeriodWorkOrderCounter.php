<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Contract;
use Illuminate\Support\Facades\DB;

/**
 * Distinct work orders with lines of a contract's offer, per renewal period
 * (spec 0358, D-15): one grouped query on the pivot.
 */
class ContractPeriodWorkOrderCounter
{
    /**
     * @return array<int, int> renewal_number => work orders count
     */
    public function countByPeriod(Contract $contract): array
    {
        return DB::table('quote_line_work_order')
            ->join('quote_lines', 'quote_lines.id', '=', 'quote_line_work_order.quote_line_id')
            ->where('quote_lines.quote_id', $contract->quote_id)
            ->groupBy('quote_line_work_order.renewal_number')
            ->selectRaw('quote_line_work_order.renewal_number as period, COUNT(DISTINCT quote_line_work_order.work_order_id) as total')
            ->pluck('total', 'period')
            ->map(fn ($total): int => (int) $total)
            ->mapWithKeys(fn (int $total, $period): array => [(int) $period => $total])
            ->all();
    }
}
