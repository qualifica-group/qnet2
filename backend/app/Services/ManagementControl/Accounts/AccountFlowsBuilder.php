<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Accounts;

use App\DataObjects\ManagementControl\AccountFlowsFilters;
use App\Services\ManagementControl\Exposure\ExposureSupport;
use Illuminate\Support\Facades\DB;

/**
 * Collections and payments of the period by financial account and by month
 * (spec 0271). A constant number of aggregate queries; amounts are integer
 * cents summed and leave as numbers with two decimals, on the document total.
 */
final class AccountFlowsBuilder
{
    public function __construct(
        private readonly CollectionAggregator $collections,
        private readonly PaymentAggregator $payments,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(AccountFlowsFilters $filters): array
    {
        // Step 1: the two sides, by account and month
        $collected = $this->cells($this->collections->rows($filters));
        $paid = $this->cells($this->payments->rows($filters));

        // Step 2: the accounts named by the rows, one query
        $accounts = $this->accounts([...array_keys($collected['accounts']), ...array_keys($paid['accounts'])]);

        return [
            'collections' => $this->side($collected, $accounts),
            'payments' => $this->side($paid, $accounts),
            // Step 3: the months of the period with both sides
            'by_month' => $this->byMonth($filters, $collected['months'], $paid['months']),
        ];
    }

    /**
     * @param  array<int, object>  $rows
     * @return array{accounts: array<int, array{installments: int, cents: int}>, months: array<string, int>}
     */
    private function cells(array $rows): array
    {
        $cells = ['accounts' => [], 'months' => []];
        foreach ($rows as $row) {
            $account = (int) $row->account_id;
            $cents = ExposureSupport::cents($row->total);
            $cells['accounts'][$account] = [
                'installments' => ($cells['accounts'][$account]['installments'] ?? 0) + (int) $row->installments,
                'cents' => ($cells['accounts'][$account]['cents'] ?? 0) + $cents,
            ];
            $cells['months'][(string) $row->flow_month] = ($cells['months'][(string) $row->flow_month] ?? 0) + $cents;
        }

        return $cells;
    }

    /**
     * Id => account for the given ids (0 = none is skipped).
     *
     * @param  array<int, int>  $ids
     * @return array<int, array{id: int, name: string, type: string}>
     */
    private function accounts(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return [];
        }

        return DB::table('financial_accounts')->whereIn('id', $ids)->get(['id', 'name', 'type'])
            ->mapWithKeys(fn (object $row): array => [(int) $row->id => ['id' => (int) $row->id, 'name' => (string) $row->name, 'type' => (string) $row->type]])
            ->all();
    }

    /**
     * One side: the total and the rows by account by amount descending, the missing account last.
     *
     * @param  array{accounts: array<int, array{installments: int, cents: int}>, months: array<string, int>}  $cells
     * @param  array<int, array{id: int, name: string, type: string}>  $accounts
     * @return array{total: float, installments: int, by_account: array<int, array<string, mixed>>}
     */
    private function side(array $cells, array $accounts): array
    {
        $ids = array_keys($cells['accounts']);
        usort($ids, function (int $a, int $b) use ($cells, $accounts): int {
            $missing = ($a === 0) <=> ($b === 0);

            return $missing ?: ($cells['accounts'][$b]['cents'] <=> $cells['accounts'][$a]['cents'])
                ?: strcasecmp($accounts[$a]['name'] ?? '', $accounts[$b]['name'] ?? '')
                ?: $a <=> $b;
        });

        return [
            'total' => ExposureSupport::euros((int) array_sum(array_column($cells['accounts'], 'cents'))),
            'installments' => (int) array_sum(array_column($cells['accounts'], 'installments')),
            'by_account' => array_map(fn (int $id): array => [
                'account' => $accounts[$id] ?? null,
                'amount' => ExposureSupport::euros($cells['accounts'][$id]['cents']),
                'installments' => $cells['accounts'][$id]['installments'],
            ], $ids),
        ];
    }

    /**
     * Every month of the period; an open side of the period is bounded by the first or last month with data.
     *
     * @param  array<string, int>  $collected  cents by month
     * @param  array<string, int>  $paid  cents by month
     * @return array<int, array{month: string, collected: float, paid: float, net: float}>
     */
    private function byMonth(AccountFlowsFilters $filters, array $collected, array $paid): array
    {
        $dataMonths = array_keys($collected + $paid);
        if ($dataMonths === []) {
            return $filters->dateFrom !== null && $filters->dateTo !== null
                ? $this->monthRows(AccountFlowsFilters::monthsBetween($filters->dateFrom, $filters->dateTo), $collected, $paid)
                : [];
        }

        sort($dataMonths);
        $from = $filters->dateFrom ?? $dataMonths[0].'-01';
        $to = $filters->dateTo ?? end($dataMonths).'-01';

        return $this->monthRows(AccountFlowsFilters::monthsBetween($from, $to), $collected, $paid);
    }

    /**
     * @param  array<int, string>  $months
     * @param  array<string, int>  $collected
     * @param  array<string, int>  $paid
     * @return array<int, array{month: string, collected: float, paid: float, net: float}>
     */
    private function monthRows(array $months, array $collected, array $paid): array
    {
        return array_map(fn (string $month): array => [
            'month' => $month,
            'collected' => ExposureSupport::euros($collected[$month] ?? 0),
            'paid' => ExposureSupport::euros($paid[$month] ?? 0),
            'net' => ExposureSupport::euros(($collected[$month] ?? 0) - ($paid[$month] ?? 0)),
        ], $months);
    }
}
