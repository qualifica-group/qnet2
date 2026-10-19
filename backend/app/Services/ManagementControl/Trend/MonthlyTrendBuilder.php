<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Trend;

use App\DataObjects\ManagementControl\MonthlyTrendFilters;
use App\Services\ManagementControl\Exposure\ExposureSupport;
use App\Services\ManagementControl\FlowSupport;
use App\Services\ManagementControl\LaborCostSource;
use App\Services\ManagementControl\WorkOrderFunctionShares;
use Illuminate\Support\Facades\DB;

/**
 * The monthly trend by business function (spec 0270): revenue, costs and margin
 * for every month of the interval, the totals against the same months one year
 * earlier and the costs by nature. The cost includes the internal labor cost
 * (spec 0272, D-4): the hours of a commessa follow its function weights, the
 * hours of no commessa go in equal shares to the functions their user belongs
 * to, structure when none (spec 0282); the nature stays on the payable
 * documents, the hours have none. Computed with a constant number of queries;
 * amounts are integer cents summed and leave as numbers with two decimals. The
 * functions always add up to the totals: a commessa's figures are split with the
 * rounding on the last share. Visibility is not scoped (D-7 of spec 0268).
 */
final class MonthlyTrendBuilder
{
    public function __construct(
        private readonly RevenueTrendAggregator $revenue,
        private readonly CostTrendAggregator $cost,
        private readonly NatureTrendAggregator $nature,
        private readonly LaborCostSource $labor,
        private readonly WorkOrderFunctionShares $shares,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(MonthlyTrendFilters $filters): array
    {
        // Step 1: the revenue and cost rows of the interval and of the previous year
        $months = $filters->months();
        $revenueRows = $this->revenue->rows($filters);
        $costRows = $this->cost->rows($filters);
        $laborRows = $this->labor->byMonth(...$filters->queryRange(), companyIds: $filters->companyIds);

        // Step 2: the function weights of the commesse the rows of the interval refer to
        $weights = $this->shares->forWorkOrders($this->workOrderIds([...$revenueRows, ...$costRows, ...$laborRows], $months));

        // Step 3: revenue and cost by function
        $revenueByFunction = $this->revenue->byFunction($revenueRows, $months, $weights);
        $costByFunction = $this->cost->byFunction($costRows, $months, $weights);
        $laborByFunction = $this->cost->byFunction($laborRows, $months, $weights, $this->memberWeights($laborRows));

        // Step 4: totals, previous year and nature
        $revenueByMonth = $this->sumByMonth($revenueRows);
        $costByMonth = $this->sumByMonth($costRows);
        $laborByMonth = $this->sumByMonth($laborRows);
        $previousMonths = array_map(MonthlyTrendFilters::previousYear(...), $months);

        return [
            'months' => $months,
            'functions' => $this->functionRows($revenueByFunction, $costByFunction, $laborByFunction, $months),
            'totals' => [
                ...$this->series($revenueByMonth, $costByMonth, $laborByMonth, $months),
                'labor_minutes' => $this->minutesSeries($laborRows, 'minutes', $months),
                'unpriced_minutes' => $this->minutesSeries($laborRows, 'unpriced_minutes', $months),
                'previous_year' => $this->series($revenueByMonth, $costByMonth, $laborByMonth, $previousMonths),
            ],
            'by_nature' => $this->nature->handle($costRows, $months, $revenueByMonth),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $months
     * @return array<int, int>
     */
    private function workOrderIds(array $rows, array $months): array
    {
        $ids = [];
        foreach ($rows as $row) {
            if ($row['work_order_id'] !== null && in_array($row['month'], $months, true)) {
                $ids[$row['work_order_id']] = $row['work_order_id'];
            }
        }

        return array_values($ids);
    }

    /**
     * Equal weights on the business functions each user of the hours of no commessa belongs to, one query.
     *
     * @param  array<int, array{user_id: int|null}>  $laborRows
     * @return array<int, array<int, int>> function weights by user id
     */
    private function memberWeights(array $laborRows): array
    {
        $userIds = array_values(array_unique(array_filter(array_column($laborRows, 'user_id'))));
        if ($userIds === []) {
            return [];
        }

        $weights = [];
        foreach (DB::table('business_function_user')->whereIn('user_id', $userIds)->get(['user_id', 'business_function_id']) as $member) {
            $weights[(int) $member->user_id][(int) $member->business_function_id] = 1;
        }

        return $weights;
    }

    /**
     * @param  array<int, array{month: string, cents: int}>  $rows
     * @return array<string, int>
     */
    private function sumByMonth(array $rows): array
    {
        $sums = [];
        foreach ($rows as $row) {
            $sums[$row['month']] = ($sums[$row['month']] ?? 0) + $row['cents'];
        }

        return $sums;
    }

    /**
     * Minutes of the given kind by month of the interval.
     *
     * @param  array<int, array{month: string, minutes: int, unpriced_minutes: int}>  $laborRows
     * @param  array<int, string>  $months
     * @return array<int, int>
     */
    private function minutesSeries(array $laborRows, string $kind, array $months): array
    {
        $byMonth = [];
        foreach ($laborRows as $row) {
            $byMonth[$row['month']] = ($byMonth[$row['month']] ?? 0) + $row[$kind];
        }

        return array_map(fn (string $month): int => $byMonth[$month] ?? 0, $months);
    }

    /**
     * @param  array<string, int>  $revenue  cents by month
     * @param  array<string, int>  $cost  payable cost cents by month
     * @param  array<string, int>  $labor  labor cost cents by month, added to the cost
     * @param  array<int, string>  $months  the months to read, in order
     * @return array{revenue: array<int, float>, cost: array<int, float>, labor: array<int, float>, margin: array<int, float>}
     */
    private function series(array $revenue, array $cost, array $labor, array $months): array
    {
        $at = fn (array $cents): array => array_map(fn (string $month): int => $cents[$month] ?? 0, $months);
        $revenueCents = $at($revenue);
        $laborCents = $at($labor);
        $costCents = array_map(fn (int $payable, int $hours): int => $payable + $hours, $at($cost), $laborCents);

        return [
            'revenue' => array_map(ExposureSupport::euros(...), $revenueCents),
            'cost' => array_map(ExposureSupport::euros(...), $costCents),
            'labor' => array_map(ExposureSupport::euros(...), $laborCents),
            'margin' => array_map(fn (int $revenue, int $cost): float => ExposureSupport::euros($revenue - $cost), $revenueCents, $costCents),
        ];
    }

    /**
     * One row per function with something to show, by label, "structure" and then "unattributed" last.
     *
     * @param  array<string, array<string, int>>  $revenue  cents by month by function key
     * @param  array<string, array<string, int>>  $cost
     * @param  array<string, array<string, int>>  $labor
     * @param  array<int, string>  $months
     * @return array<int, array<string, mixed>>
     */
    private function functionRows(array $revenue, array $cost, array $labor, array $months): array
    {
        $keys = array_keys($revenue + $cost + $labor);
        $names = $this->functionNames($keys);
        $rows = [];

        foreach ($keys as $key) {
            if (array_sum(array_map('abs', $revenue[$key] ?? [])) + array_sum(array_map('abs', $cost[$key] ?? [])) + array_sum(array_map('abs', $labor[$key] ?? [])) === 0) {
                continue;
            }

            $rows[] = ['key' => (string) $key, 'label' => $names[$key] ?? null, ...$this->series($revenue[$key] ?? [], $cost[$key] ?? [], $labor[$key] ?? [], $months)];
        }

        usort($rows, fn (array $a, array $b): int => $this->rank($a['key']) <=> $this->rank($b['key']) ?: strcasecmp((string) $a['label'], (string) $b['label']));

        return $rows;
    }

    private function rank(string $key): int
    {
        return match ($key) {
            CostTrendAggregator::STRUCTURE => 1,
            FlowSupport::UNATTRIBUTED => 2,
            default => 0,
        };
    }

    /**
     * Names of the business functions among the keys, one query.
     *
     * @param  array<int, int|string>  $keys
     * @return array<int|string, string>
     */
    private function functionNames(array $keys): array
    {
        $ids = array_values(array_filter($keys, fn (int|string $key): bool => $this->rank((string) $key) === 0));
        if ($ids === []) {
            return [];
        }

        return DB::table('business_functions')->whereIn('id', $ids)->pluck('name', 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])->all();
    }
}
