<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\CashFlow;

use App\DataObjects\ManagementControl\CashFlowFilters;
use App\DataObjects\ManagementControl\ExposureFilters;
use App\Services\ManagementControl\FlowSupport;
use Illuminate\Support\Facades\DB;

/**
 * Expected payments (spec 0270, D-1): the open residual of the payable
 * installments, already the amount payable net of withholding and ENASARCO
 * (spec 0269, D-3), by company and expected month.
 */
final class PayableFlowAggregator
{
    private const array SCOPE_COLUMNS = [
        'company' => 'purchase_invoices.company_id',
        'company_site' => 'purchase_invoices.company_site_id',
        'operational_site' => 'purchase_invoices.operational_site_id',
    ];

    /**
     * @return array<int, array<string, int>> cents by bucket by company id (0 = none)
     */
    public function cents(CashFlowFilters $filters, string $today): array
    {
        $query = DB::table('purchase_invoice_installments')
            ->join('purchase_invoices', 'purchase_invoices.id', '=', 'purchase_invoice_installments.purchase_invoice_id')
            ->when($filters->documentType !== ExposureFilters::TYPE_ALL, fn ($q) => $q->where('purchase_invoices.document_type', $filters->documentType));

        return FlowSupport::installmentCents(
            FlowSupport::scope($query, $filters->companyIds, $filters->companySiteIds, $filters->operationalSiteIds, self::SCOPE_COLUMNS),
            'purchase_invoice_installments.due_date',
            'purchase_invoice_installments.residual_amount',
            'purchase_invoices.company_id',
            $today,
        );
    }
}
