<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Exposure;

use App\DataObjects\ManagementControl\ExposureFilters;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The receivable side (spec 0269): `invoice_installments` of the active
 * invoicing documents. The sites of a document are those of its offer (D-11),
 * so a document without offer has none.
 */
final class ReceivableScheduleAggregator extends ScheduleAggregator
{
    protected function installments(): Builder
    {
        return DB::table('invoice_installments')
            ->join('invoices', 'invoices.id', '=', 'invoice_installments.invoice_id')
            ->leftJoin('quotes', 'quotes.id', '=', 'invoices.quote_id');
    }

    protected function documents(): Builder
    {
        return DB::table('invoices')->leftJoin('quotes', 'quotes.id', '=', 'invoices.quote_id');
    }

    public function columns(): array
    {
        return [
            'installments' => 'invoice_installments',
            'documents' => 'invoices',
            'fk' => 'invoice_id',
            'registry' => 'invoices.customer_registry_id',
            'due' => 'invoice_installments.due_date',
            'residual' => 'invoice_installments.residual_amount',
            'settled_amount' => 'invoice_installments.collected_amount',
            'settled_at' => 'invoice_installments.collected_at',
            'document_date' => 'invoices.document_date',
            'invoiced' => 'invoices.total_amount',
            'type' => 'invoices.type',
            ExposureFilters::GROUP_COMPANY => 'invoices.company_id',
            ExposureFilters::GROUP_COMPANY_SITE => 'quotes.company_site_id',
            ExposureFilters::GROUP_OPERATIONAL_SITE => 'quotes.operational_site_id',
        ];
    }
}
