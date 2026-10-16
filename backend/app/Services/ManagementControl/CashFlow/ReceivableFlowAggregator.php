<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\CashFlow;

use App\DataObjects\ManagementControl\CashFlowFilters;
use App\DataObjects\ManagementControl\ExposureFilters;
use App\Services\ManagementControl\FlowSupport;
use Illuminate\Support\Facades\DB;

/**
 * Expected collections (spec 0270, D-1): the open residual of the installments
 * of the active invoicing documents, by company and expected month. The sites
 * of a document are those of its offer, so a document without offer has none.
 */
final class ReceivableFlowAggregator
{
    private const array SCOPE_COLUMNS = [
        'company' => 'invoices.company_id',
        'company_site' => 'quotes.company_site_id',
        'operational_site' => 'quotes.operational_site_id',
    ];

    /**
     * @return array<int, array<string, int>> cents by bucket by company id (0 = none)
     */
    public function cents(CashFlowFilters $filters, string $today): array
    {
        $query = DB::table('invoice_installments')
            ->join('invoices', 'invoices.id', '=', 'invoice_installments.invoice_id')
            ->leftJoin('quotes', 'quotes.id', '=', 'invoices.quote_id')
            ->when($filters->documentType !== ExposureFilters::TYPE_ALL, fn ($q) => $q->where('invoices.type', $filters->documentType));

        return FlowSupport::installmentCents(
            FlowSupport::scope($query, $filters->companyIds, $filters->companySiteIds, $filters->operationalSiteIds, self::SCOPE_COLUMNS),
            'invoice_installments.due_date',
            'invoice_installments.residual_amount',
            'invoices.company_id',
            $today,
        );
    }
}
