<?php

namespace App\Tables\PayrollCosts;

use App\Models\PayrollCost;
use App\Services\ManagementControl\Payroll\PayrollMonthMinutes;
use Illuminate\Support\Collection;

/**
 * The computed columns of a payroll cost row (spec 0430 D-11), worked out from the person-month timesheet:
 * the minutes of the whole page come from ONE aggregate query, then each row reads its own pair.
 */
final class PayrollCostComputedColumns
{
    private const int MINUTES_PER_HOUR = 60;

    private const int CENTS_PER_UNIT = 100;

    /**
     * Stamp the timesheet minutes of each row's person-month on the page (the Eloquent `afterQuery` callback).
     *
     * @param  Collection<int, PayrollCost>  $page
     */
    public static function stampMinutes(Collection $page): void
    {
        $pairs = $page->map(static fn (PayrollCost $row): array => [(int) $row->user_id, (string) $row->period])->values()->all();
        $minutes = app(PayrollMonthMinutes::class)->forPairs($pairs);

        foreach ($page as $row) {
            $pair = $minutes[PayrollMonthMinutes::key((int) $row->user_id, (string) $row->period)] ?? null;
            $row->setAttribute('timesheet_minutes', $pair['total_minutes'] ?? 0);
            $row->setAttribute('work_order_minutes', $pair['work_order_minutes'] ?? 0);
        }
    }

    /**
     * @return array{timesheet_hours: string, work_order_hours: string, allocated_cost: string, unallocated_cost: string}
     */
    public static function forRow(PayrollCost $row): array
    {
        $total = (int) $row->getAttribute('timesheet_minutes');
        $workOrder = (int) $row->getAttribute('work_order_minutes');
        $totalCents = (int) round((float) $row->total_cost * self::CENTS_PER_UNIT);
        $allocatedCents = $total === 0 ? 0 : (int) round($totalCents * $workOrder / $total);

        return [
            'timesheet_hours' => self::hours($total),
            'work_order_hours' => self::hours($workOrder),
            'allocated_cost' => self::money($allocatedCents),
            'unallocated_cost' => self::money($totalCents - $allocatedCents),
        ];
    }

    private static function hours(int $minutes): string
    {
        return number_format($minutes / self::MINUTES_PER_HOUR, 2, '.', '');
    }

    private static function money(int $cents): string
    {
        return number_format($cents / self::CENTS_PER_UNIT, 2, '.', '');
    }
}
