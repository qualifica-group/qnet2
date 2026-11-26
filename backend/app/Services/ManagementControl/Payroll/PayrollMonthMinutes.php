<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Payroll;

use Illuminate\Support\Facades\DB;

/**
 * The timesheet minutes of the (user, month) pairs of the payroll (spec 0430 D-7, D-11): the divisor of the real
 * hourly cost and the base of the share charged to the work orders. One aggregate query, whatever the pairs.
 */
final class PayrollMonthMinutes
{
    public static function key(int $userId, string $period): string
    {
        return "{$userId}|{$period}";
    }

    /**
     * Minutes of each pair, keyed by `self::key(user_id, period)`; a pair with no entries is absent (read it as zero).
     *
     * @param  list<array{0: int, 1: string}>  $pairs  [user_id, period "YYYY-MM"]
     * @return array<string, array{total_minutes: int, work_order_minutes: int}>
     */
    public function forPairs(array $pairs): array
    {
        if ($pairs === []) {
            return [];
        }

        $wanted = [];
        foreach ($pairs as [$userId, $period]) {
            $wanted[self::key($userId, $period)] = true;
        }

        $rows = DB::table('time_entries')
            ->selectRaw(
                'user_id, SUBSTR(date, 1, 7) AS period, SUM(minutes) AS total_minutes, '
                .'SUM(CASE WHEN work_order_id IS NULL THEN 0 ELSE minutes END) AS work_order_minutes',
            )
            ->whereIn('user_id', array_values(array_unique(array_column($pairs, 0))))
            ->whereIn(DB::raw('SUBSTR(date, 1, 7)'), array_values(array_unique(array_column($pairs, 1))))
            ->groupBy('user_id', 'period')
            ->get();

        $minutes = [];
        foreach ($rows as $row) {
            $key = self::key((int) $row->user_id, (string) $row->period);
            if (isset($wanted[$key])) {
                $minutes[$key] = ['total_minutes' => (int) $row->total_minutes, 'work_order_minutes' => (int) $row->work_order_minutes];
            }
        }

        return $minutes;
    }
}
