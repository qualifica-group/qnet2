<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\CashFlow;

use App\DataObjects\ManagementControl\CashFlowFilters;
use App\Services\ManagementControl\Exposure\ExposureSupport;
use App\Services\ManagementControl\FlowSupport;
use Carbon\CarbonImmutable;

/**
 * The cash flow forecast (spec 0270): the open installments and commitments at
 * today, projected by expected month. Everything left of today is the backlog,
 * the horizon starts at the current month and what falls after it is "beyond".
 * A constant number of aggregate queries (the aggregators); amounts are integer
 * cents summed and leave as numbers with two decimals, on the document total.
 */
final class CashFlowBuilder
{
    private const string BEYOND = 'beyond';

    private const array SIDES = ['receivable', 'payable', 'committed'];

    public function __construct(
        private readonly ReceivableFlowAggregator $receivable,
        private readonly PayableFlowAggregator $payable,
        private readonly CommittedFlowAggregator $committed,
    ) {}

    /**
     * @param  string  $today  "Y-m-d", the day the snapshot refers to
     * @return array<string, mixed>
     */
    public function handle(CashFlowFilters $filters, string $today): array
    {
        // Step 1: the three sources, by company and expected bucket
        $sources = [
            'receivable' => $this->receivable->cents($filters, $today),
            'payable' => $this->payable->cents($filters, $today),
            'committed' => $this->committed->cents($filters, $today),
        ];

        // Step 2: the columns of the projection and the cells (company x column x side)
        $months = $this->months($today, $filters->horizonMonths);
        $cells = $this->cells($sources, $months);
        $columns = [FlowSupport::BACKLOG, ...$months, self::BEYOND];

        // Step 3: the flows in total, with the running balance of the months
        $totals = array_fill_keys($columns, array_fill_keys(self::SIDES, 0));
        foreach ($cells as $byColumn) {
            foreach ($byColumn as $column => $sides) {
                foreach ($sides as $side => $cents) {
                    $totals[$column][$side] += $cents;
                }
            }
        }

        $cumulative = 0;
        $monthRows = [];
        foreach ($months as $month) {
            $cumulative += $this->net($totals[$month]);
            $monthRows[] = ['month' => $month, ...$this->flow($totals[$month]), 'cumulative' => ExposureSupport::euros($cumulative)];
        }

        return [
            'today' => $today,
            'backlog' => $this->flow($totals[FlowSupport::BACKLOG]),
            'months' => $monthRows,
            'beyond' => $this->flow($totals[self::BEYOND]),
            'by_company' => $this->byCompany($cells, $months),
        ];
    }

    /**
     * "YYYY-MM" of the horizon, from the month of today.
     *
     * @return array<int, string>
     */
    private function months(string $today, int $count): array
    {
        $first = CarbonImmutable::parse($today)->startOfMonth();

        return array_map(fn (int $offset): string => $first->addMonthsNoOverflow($offset)->format('Y-m'), range(0, $count - 1));
    }

    /**
     * Cents by company id, column (backlog, month or beyond) and side; every
     * bucket after the horizon goes to "beyond".
     *
     * @param  array<string, array<int, array<string, int>>>  $sources
     * @param  array<int, string>  $months
     * @return array<int, array<string, array<string, int>>>
     */
    private function cells(array $sources, array $months): array
    {
        $cells = [];
        foreach ($sources as $side => $byCompany) {
            foreach ($byCompany as $companyId => $byBucket) {
                foreach ($byBucket as $bucket => $cents) {
                    $column = $bucket === FlowSupport::BACKLOG || in_array($bucket, $months, true) ? $bucket : self::BEYOND;
                    $cells[$companyId][$column][$side] = ($cells[$companyId][$column][$side] ?? 0) + $cents;
                }
            }
        }

        return $cells;
    }

    /**
     * One row per company with something to show, by label with "unattributed" last (D-5).
     *
     * @param  array<int, array<string, array<string, int>>>  $cells
     * @param  array<int, string>  $months
     * @return array<int, array<string, mixed>>
     */
    private function byCompany(array $cells, array $months): array
    {
        $names = FlowSupport::companyNames(array_values(array_filter(array_keys($cells))));
        $rows = [];
        foreach ($cells as $companyId => $byColumn) {
            $net = fn (string $column): int => $this->net(array_merge(array_fill_keys(self::SIDES, 0), $byColumn[$column] ?? []));
            $monthNets = array_map(fn (string $month): array => ['month' => $month, 'net' => ExposureSupport::euros($net($month))], $months);
            $total = $net(FlowSupport::BACKLOG) + $net(self::BEYOND) + array_sum(array_map($net, $months));

            $rows[] = [
                'key' => $companyId === 0 ? FlowSupport::UNATTRIBUTED : (string) $companyId,
                'label' => $companyId === 0 ? null : ($names[$companyId] ?? (string) $companyId),
                'backlog_net' => ExposureSupport::euros($net(FlowSupport::BACKLOG)),
                'months' => $monthNets,
                'beyond_net' => ExposureSupport::euros($net(self::BEYOND)),
                'total_net' => ExposureSupport::euros($total),
            ];
        }

        usort($rows, function (array $a, array $b): int {
            $unattributed = ($a['key'] === FlowSupport::UNATTRIBUTED) <=> ($b['key'] === FlowSupport::UNATTRIBUTED);

            return $unattributed ?: strcasecmp((string) $a['label'], (string) $b['label']);
        });

        return $rows;
    }

    /**
     * @param  array<string, int>  $sides  cents by side
     * @return array{receivable: float, payable: float, committed: float, net: float}
     */
    private function flow(array $sides): array
    {
        return [
            'receivable' => ExposureSupport::euros($sides['receivable']),
            'payable' => ExposureSupport::euros($sides['payable']),
            'committed' => ExposureSupport::euros($sides['committed']),
            'net' => ExposureSupport::euros($this->net($sides)),
        ];
    }

    /**
     * @param  array<string, int>  $sides
     */
    private function net(array $sides): int
    {
        return $sides['receivable'] - $sides['payable'] - $sides['committed'];
    }
}
