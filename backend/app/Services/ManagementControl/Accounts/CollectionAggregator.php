<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Accounts;

use App\DataObjects\ManagementControl\AccountFlowsFilters;
use App\Services\ManagementControl\FlowSupport;
use Illuminate\Support\Facades\DB;

/**
 * The collections of the period (spec 0271, D-6): the collected amount of the
 * active installments by collection date, on the account of the invoice. The
 * sites of a document are those of its offer.
 */
final class CollectionAggregator
{
    private const array SCOPE_COLUMNS = [
        'company' => 'invoices.company_id',
        'company_site' => 'quotes.company_site_id',
        'operational_site' => 'quotes.operational_site_id',
    ];

    /**
     * @return array<int, object{account_id: int|null, flow_month: string, installments: int, total: string}>
     */
    public function rows(AccountFlowsFilters $filters): array
    {
        $query = DB::table('invoice_installments')
            ->join('invoices', 'invoices.id', '=', 'invoice_installments.invoice_id')
            ->leftJoin('quotes', 'quotes.id', '=', 'invoices.quote_id')
            ->whereNotNull('invoice_installments.collected_at')
            ->when($filters->dateFrom !== null, fn ($q) => $q->where('invoice_installments.collected_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo !== null, fn ($q) => $q->where('invoice_installments.collected_at', '<', $filters->dateToExclusive()));

        return FlowSupport::scope($query, $filters->companyIds, $filters->companySiteIds, $filters->operationalSiteIds, self::SCOPE_COLUMNS)
            ->selectRaw('invoices.financial_account_id AS account_id, SUBSTR(invoice_installments.collected_at, 1, 7) AS flow_month, COUNT(*) AS installments, SUM(invoice_installments.collected_amount) AS total')
            ->groupBy('account_id', 'flow_month')
            ->get()
            ->all();
    }
}
