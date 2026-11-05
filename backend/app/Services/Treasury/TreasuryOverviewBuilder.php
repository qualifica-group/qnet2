<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Enums\FinancialAccountType;
use App\Enums\TreasuryMovementDirection;
use App\Enums\TreasuryMovementType;
use App\Services\ManagementControl\Exposure\PayableScheduleAggregator;
use App\Services\ManagementControl\Exposure\ReceivableScheduleAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The treasury overview (spec 0362, "Quadro finanziario"): liquidity, open
 * receivables and payables with overdue buckets, net position, the 30/60/90
 * days forecast from the real balance and what is left to verify. Read-only;
 * balances come from the single balance rule of `AccountStatementService`.
 */
final class TreasuryOverviewBuilder
{
    public function __construct(
        private readonly AccountStatementService $statements,
        private readonly TreasuryScheduleSnapshot $schedule,
        private readonly ReceivableScheduleAggregator $receivable,
        private readonly PayableScheduleAggregator $payable,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(?int $companyId, CarbonImmutable $today): array
    {
        $date = $today->toDateString();

        // Step 1: liquidity of the accounts at today
        $liquidity = $this->liquidity($companyId, $today);

        // Step 2: open receivables and payables with the overdue buckets
        $receivable = $this->schedule->aging($this->receivable, $companyId, $date);
        $payable = $this->schedule->aging($this->payable, $companyId, $date);

        // Step 3: the forecast windows, starting from the real liquidity (D-2)
        $forecast = $this->forecast($liquidity['cents'], $companyId, $date);

        // Step 4: what is left to verify
        return [
            'today' => $date,
            'company_id' => $companyId,
            'liquidity' => $liquidity['payload'],
            'receivable' => $this->side($receivable),
            'payable' => $this->side($payable),
            'net_position' => AccountStatementFacts::toDecimal($liquidity['cents'] + $receivable['open'] - $payable['open']),
            'forecast' => $forecast,
            'to_verify' => [
                'unassigned' => $this->unassigned($companyId),
                'adjustments' => $this->adjustments($companyId, $today),
            ],
        ];
    }

    /**
     * @return array{cents: int, payload: array<string, mixed>}
     */
    private function liquidity(?int $companyId, CarbonImmutable $today): array
    {
        $balances = $this->statements->balances($companyId, $today);
        $byType = array_fill_keys(FinancialAccountType::values(), 0);
        foreach ($balances['accounts'] as $account) {
            $byType[$account['type']] += AccountStatementFacts::toCents($account['balance']);
        }
        $total = array_sum($byType);

        return ['cents' => $total, 'payload' => [
            'total' => AccountStatementFacts::toDecimal($total),
            'by_type' => array_map(AccountStatementFacts::toDecimal(...), $byType),
            'accounts' => $balances['accounts'],
        ]];
    }

    /**
     * @param  array{open: int, overdue: array<string, int>}  $aging
     * @return array<string, mixed>
     */
    private function side(array $aging): array
    {
        $overdue = array_sum($aging['overdue']);

        return [
            'open_total' => AccountStatementFacts::toDecimal($aging['open']),
            'not_due_total' => AccountStatementFacts::toDecimal($aging['open'] - $overdue),
            'overdue_total' => AccountStatementFacts::toDecimal($overdue),
            'overdue' => array_map(AccountStatementFacts::toDecimal(...), $aging['overdue']),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function forecast(int $liquidityCents, ?int $companyId, string $today): array
    {
        $in = $this->schedule->forecast($this->receivable, $companyId, $today);
        $out = $this->schedule->forecast($this->payable, $companyId, $today);

        return array_map(fn (int $days): array => [
            'days' => $days,
            'until' => TreasuryScheduleSnapshot::until($today, $days),
            'receivable' => AccountStatementFacts::toDecimal($in[$days]),
            'payable' => AccountStatementFacts::toDecimal($out[$days]),
            'expected_balance' => AccountStatementFacts::toDecimal($liquidityCents + $in[$days] - $out[$days]),
        ], TreasuryScheduleSnapshot::FORECAST_DAYS);
    }

    /**
     * @return array{count: int, collections: string, payments: string}
     */
    private function unassigned(?int $companyId): array
    {
        $unassigned = $this->statements->unassigned($companyId);
        $sums = ['collection' => 0, 'payment' => 0];
        foreach ($unassigned['rows'] as $row) {
            $sums[$row['source']] += AccountStatementFacts::toCents($row['amount']);
        }

        return [
            'count' => $unassigned['count'],
            'collections' => AccountStatementFacts::toDecimal($sums['collection']),
            'payments' => AccountStatementFacts::toDecimal($sums['payment']),
        ];
    }

    /**
     * Balance adjustments dated in the current month.
     *
     * @return array{month: string, count: int, inflow: string, outflow: string}
     */
    private function adjustments(?int $companyId, CarbonImmutable $today): array
    {
        $rows = DB::table('treasury_movements')
            ->join('financial_accounts', 'financial_accounts.id', '=', 'treasury_movements.financial_account_id')
            ->where('treasury_movements.type', TreasuryMovementType::Adjustment->value)
            ->where('treasury_movements.movement_date', '>=', $today->startOfMonth()->toDateString())
            ->where('treasury_movements.movement_date', '<', $today->startOfMonth()->addMonthNoOverflow()->toDateString())
            ->when($companyId !== null, fn ($query) => $query->where('financial_accounts.company_id', $companyId))
            ->get(['treasury_movements.direction', 'treasury_movements.amount']);

        $inflow = 0;
        $outflow = 0;
        foreach ($rows as $row) {
            $cents = AccountStatementFacts::toCents((string) $row->amount);
            $row->direction === TreasuryMovementDirection::In->value ? $inflow += $cents : $outflow += $cents;
        }

        return [
            'month' => $today->format('Y-m'),
            'count' => $rows->count(),
            'inflow' => AccountStatementFacts::toDecimal($inflow),
            'outflow' => AccountStatementFacts::toDecimal($outflow),
        ];
    }
}
