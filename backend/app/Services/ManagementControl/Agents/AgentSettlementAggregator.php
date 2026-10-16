<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Agents;

use App\DataObjects\ManagementControl\AgentsFilters;
use App\Enums\CommissionSettlementStatus;
use App\Services\ManagementControl\Exposure\ExposureSupport;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The settlement statements of the agents (spec 0272, D-5), as registered:
 * what sits in an issued statement or one with the document received, what is
 * approved and not yet paid (the payable invoice of the statement holds the
 * payments), and what was paid in the period. Statements are stock figures as
 * of today; drafts and cancelled ones never count.
 */
final class AgentSettlementAggregator
{
    /**
     * @return array{
     *     in_statement: array<int, int>, pending_document: array<int, int>, approved_net: array<int, int>,
     *     approved_paid: array<int, int>, paid: array<int, int>, paid_by_month: array<string, int>
     * } cents (counts for pending_document) by referent id / month
     */
    public function handle(AgentsFilters $filters): array
    {
        $inStatement = [];
        $pending = [];
        $approvedNet = [];
        foreach ($this->statementRows($filters) as $row) {
            $referent = (int) $row->referent_id;
            $cents = ExposureSupport::cents($row->total);

            if ($row->status === CommissionSettlementStatus::Approved->value) {
                $approvedNet[$referent] = $cents;

                continue;
            }

            $inStatement[$referent] = ($inStatement[$referent] ?? 0) + $cents;
            if ($row->status === CommissionSettlementStatus::Issued->value) {
                $pending[$referent] = (int) $row->statements;
            }
        }

        $approvedPaid = [];
        foreach ($this->approvedPaidRows($filters) as $row) {
            $approvedPaid[(int) $row->referent_id] = ExposureSupport::cents($row->total);
        }

        $paid = [];
        $paidByMonth = [];
        foreach ($this->paidRows($filters) as $row) {
            $cents = ExposureSupport::cents($row->total);
            $paid[(int) $row->referent_id] = ($paid[(int) $row->referent_id] ?? 0) + $cents;
            $paidByMonth[(string) $row->paid_month] = ($paidByMonth[(string) $row->paid_month] ?? 0) + $cents;
        }

        return [
            'in_statement' => $inStatement,
            'pending_document' => $pending,
            'approved_net' => $approvedNet,
            'approved_paid' => $approvedPaid,
            'paid' => $paid,
            'paid_by_month' => $paidByMonth,
        ];
    }

    /**
     * @return array<int, object{referent_id: int, status: string, statements: int, total: string}>
     */
    private function statementRows(AgentsFilters $filters): array
    {
        return $this->statements($filters)
            ->selectRaw('commission_settlements.referent_id AS referent_id, commission_settlements.status AS status, COUNT(*) AS statements, SUM(commission_settlements.net_payable) AS total')
            ->groupBy('referent_id', 'status')
            ->get()
            ->all();
    }

    /**
     * Everything paid so far on the payable invoice of the approved statements.
     *
     * @return array<int, object{referent_id: int, total: string}>
     */
    private function approvedPaidRows(AgentsFilters $filters): array
    {
        return $this->paidInstallments($filters)
            ->where('commission_settlements.status', CommissionSettlementStatus::Approved->value)
            ->selectRaw('commission_settlements.referent_id AS referent_id, SUM(purchase_invoice_installments.paid_amount) AS total')
            ->groupBy('referent_id')
            ->get()
            ->all();
    }

    /**
     * Payments dated in the period on the payable invoice of the statements.
     *
     * @return array<int, object{referent_id: int, paid_month: string, total: string}>
     */
    private function paidRows(AgentsFilters $filters): array
    {
        return $this->paidInstallments($filters)
            ->whereNotNull('purchase_invoice_installments.paid_at')
            ->when($filters->dateFrom !== null, fn (Builder $q) => $q->where('purchase_invoice_installments.paid_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo !== null, fn (Builder $q) => $q->where('purchase_invoice_installments.paid_at', '<', $filters->dateToExclusive()))
            ->selectRaw('commission_settlements.referent_id AS referent_id, SUBSTR(purchase_invoice_installments.paid_at, 1, 7) AS paid_month, SUM(purchase_invoice_installments.paid_amount) AS total')
            ->groupBy('referent_id', 'paid_month')
            ->get()
            ->all();
    }

    /** The statements that count: issued, with the document received or approved, of the companies asked for. */
    private function statements(AgentsFilters $filters): Builder
    {
        return DB::table('commission_settlements')
            ->whereIn('commission_settlements.status', [
                CommissionSettlementStatus::Issued->value,
                CommissionSettlementStatus::DocumentReceived->value,
                CommissionSettlementStatus::Approved->value,
            ])
            ->when($filters->companyIds !== [], fn (Builder $q) => $q->whereIn('commission_settlements.company_id', $filters->companyIds));
    }

    private function paidInstallments(AgentsFilters $filters): Builder
    {
        return $this->statements($filters)
            ->join('purchase_invoice_installments', 'purchase_invoice_installments.purchase_invoice_id', '=', 'commission_settlements.purchase_invoice_id');
    }
}
