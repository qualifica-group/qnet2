<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Accounts;

use App\DataObjects\ManagementControl\AccountFlowsFilters;
use App\Services\ManagementControl\FlowSupport;
use Illuminate\Support\Facades\DB;

/**
 * The payments of the period (spec 0271, D-6): the paid amount of the payable
 * installments by payment date, on the account of the installment or, when it
 * has none, of the document. Sites and company come from the document header.
 */
final class PaymentAggregator
{
    private const array SCOPE_COLUMNS = [
        'company' => 'purchase_invoices.company_id',
        'company_site' => 'purchase_invoices.company_site_id',
        'operational_site' => 'purchase_invoices.operational_site_id',
    ];

    /**
     * @return array<int, object{account_id: int|null, flow_month: string, installments: int, total: string}>
     */
    public function rows(AccountFlowsFilters $filters): array
    {
        $query = DB::table('purchase_invoice_installments')
            ->join('purchase_invoices', 'purchase_invoices.id', '=', 'purchase_invoice_installments.purchase_invoice_id')
            ->whereNotNull('purchase_invoice_installments.paid_at')
            ->when($filters->dateFrom !== null, fn ($q) => $q->where('purchase_invoice_installments.paid_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo !== null, fn ($q) => $q->where('purchase_invoice_installments.paid_at', '<', $filters->dateToExclusive()));

        return FlowSupport::scope($query, $filters->companyIds, $filters->companySiteIds, $filters->operationalSiteIds, self::SCOPE_COLUMNS)
            ->selectRaw('COALESCE(purchase_invoice_installments.financial_account_id, purchase_invoices.financial_account_id) AS account_id, SUBSTR(purchase_invoice_installments.paid_at, 1, 7) AS flow_month, COUNT(*) AS installments, SUM(purchase_invoice_installments.paid_amount) AS total')
            ->groupBy('account_id', 'flow_month')
            ->get()
            ->all();
    }
}
