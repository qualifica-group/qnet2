<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Trend;

use App\Enums\CostTypeNature;
use App\Services\ManagementControl\Exposure\ExposureSupport;

/**
 * The costs by nature of their cost type (spec 0270, D-9), from the cost rows
 * the trend already loaded. The three natures are always present, in this
 * order; the row without a cost type ("null" nature) only when it has costs.
 */
final class NatureTrendAggregator
{
    /**
     * @param  array<int, array{month: string, work_order_id: int|null, nature: string|null, cents: int}>  $costRows
     * @param  array<int, string>  $months
     * @param  array<string, int>  $revenueByMonth  revenue cents by month
     * @return array<int, array{nature: string|null, cost: array<int, float>, share_of_revenue: array<int, float|null>}>
     */
    public function handle(array $costRows, array $months, array $revenueByMonth): array
    {
        $byNature = [];
        foreach ($costRows as $row) {
            if (in_array($row['month'], $months, true)) {
                $key = $row['nature'] ?? '';
                $byNature[$key][$row['month']] = ($byNature[$key][$row['month']] ?? 0) + $row['cents'];
            }
        }

        $natures = array_map(fn (CostTypeNature $nature): string => $nature->value, CostTypeNature::cases());
        if (array_sum($byNature[''] ?? []) !== 0) {
            $natures[] = '';
        }

        return array_map(function (string $key) use ($byNature, $months, $revenueByMonth): array {
            $cents = array_map(fn (string $month): int => $byNature[$key][$month] ?? 0, $months);

            return [
                'nature' => $key === '' ? null : $key,
                'cost' => array_map(ExposureSupport::euros(...), $cents),
                'share_of_revenue' => array_map(
                    fn (int $cost, string $month): ?float => ($revenueByMonth[$month] ?? 0) === 0 ? null : round($cost / $revenueByMonth[$month] * 100, 1),
                    $cents,
                    $months,
                ),
            ];
        }, $natures);
    }
}
