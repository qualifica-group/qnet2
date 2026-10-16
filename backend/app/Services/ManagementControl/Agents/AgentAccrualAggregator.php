<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Agents;

use App\DataObjects\ManagementControl\AgentsFilters;
use App\Services\ManagementControl\Exposure\ExposureSupport;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The commission ledger of the agents (spec 0272, D-5): what accrued in the
 * period (signed sum, so reversals and adjustments count) by agent and month,
 * and what is still to settle as of today. The agent is the referent
 * recipient of the movement, whatever the role.
 */
final class AgentAccrualAggregator
{
    private const string RECIPIENT_TYPE = 'referent';

    /**
     * @return array{accrued: array<int, int>, accrued_by_month: array<string, int>, to_settle: array<int, int>} cents by referent id / month
     */
    public function handle(AgentsFilters $filters): array
    {
        $accrued = [];
        $accruedByMonth = [];
        foreach ($this->accruedRows($filters) as $row) {
            $cents = ExposureSupport::cents($row->total);
            $accrued[(int) $row->referent_id] = ($accrued[(int) $row->referent_id] ?? 0) + $cents;
            $accruedByMonth[(string) $row->accrued_month] = ($accruedByMonth[(string) $row->accrued_month] ?? 0) + $cents;
        }

        $toSettle = [];
        foreach ($this->toSettleRows($filters) as $row) {
            $toSettle[(int) $row->referent_id] = ExposureSupport::cents($row->total);
        }

        return ['accrued' => $accrued, 'accrued_by_month' => $accruedByMonth, 'to_settle' => $toSettle];
    }

    /**
     * @return array<int, object{referent_id: int, accrued_month: string, total: string}>
     */
    private function accruedRows(AgentsFilters $filters): array
    {
        return $this->base($filters)
            ->when($filters->dateFrom !== null, fn (Builder $q) => $q->where('accrued_on', '>=', $filters->dateFrom))
            ->when($filters->dateTo !== null, fn (Builder $q) => $q->where('accrued_on', '<', $filters->dateToExclusive()))
            ->selectRaw('recipient_id AS referent_id, SUBSTR(accrued_on, 1, 7) AS accrued_month, SUM(amount) AS total')
            ->groupBy('referent_id', 'accrued_month')
            ->get()
            ->all();
    }

    /**
     * Movements still on no statement, as of today, whatever their date; the ones settled outside are not owed.
     *
     * @return array<int, object{referent_id: int, total: string}>
     */
    private function toSettleRows(AgentsFilters $filters): array
    {
        return $this->base($filters)
            ->whereNull('commission_settlement_id')
            ->whereNull('settled_externally_on')
            ->selectRaw('recipient_id AS referent_id, SUM(amount) AS total')
            ->groupBy('referent_id')
            ->get()
            ->all();
    }

    private function base(AgentsFilters $filters): Builder
    {
        return DB::table('commission_accruals')
            ->where('recipient_type', self::RECIPIENT_TYPE)
            ->when($filters->companyIds !== [], fn (Builder $q) => $q->whereIn('company_id', $filters->companyIds));
    }
}
