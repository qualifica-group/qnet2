<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Exposure;

use App\DataObjects\ManagementControl\ExposureFilters;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The payable side (spec 0269): `purchase_invoice_installments`, already the
 * amount payable net of withholding and ENASARCO. The invoices of commission
 * statements are included: they are real debts towards the agents (D-9 of
 * spec 0268 excludes them from the costs, not from the schedule).
 */
final class PayableScheduleAggregator extends ScheduleAggregator
{
    /**
     * @return array<string, mixed>
     */
    public function side(ExposureFilters $filters, string $today): array
    {
        return [...parent::side($filters, $today), 'by_cost_type' => $this->byCostType($filters, $today)];
    }

    protected function installments(): Builder
    {
        return DB::table('purchase_invoice_installments')
            ->join('purchase_invoices', 'purchase_invoices.id', '=', 'purchase_invoice_installments.purchase_invoice_id');
    }

    protected function documents(): Builder
    {
        return DB::table('purchase_invoices');
    }

    public function columns(): array
    {
        return [
            'installments' => 'purchase_invoice_installments',
            'documents' => 'purchase_invoices',
            'fk' => 'purchase_invoice_id',
            'registry' => 'purchase_invoices.supplier_id',
            'due' => 'purchase_invoice_installments.due_date',
            'residual' => 'purchase_invoice_installments.residual_amount',
            'settled_amount' => 'purchase_invoice_installments.paid_amount',
            'settled_at' => 'purchase_invoice_installments.paid_at',
            'document_date' => 'purchase_invoices.document_date',
            'invoiced' => 'purchase_invoices.total_amount - purchase_invoices.withholding_amount - purchase_invoices.enasarco_amount',
            'type' => 'purchase_invoices.document_type',
            ExposureFilters::GROUP_COMPANY => 'purchase_invoices.company_id',
            ExposureFilters::GROUP_COMPANY_SITE => 'purchase_invoices.company_site_id',
            ExposureFilters::GROUP_OPERATIONAL_SITE => 'purchase_invoices.operational_site_id',
        ];
    }

    /**
     * Open and overdue amount by the cost type of the document.
     *
     * @return array<int, array<string, mixed>>
     */
    private function byCostType(ExposureFilters $filters, string $today): array
    {
        [$totals, $bindings] = AgingSql::totals($this->columns()['due'], $this->columns()['residual'], $today);
        $rows = $this->scopedInstallments($filters)
            ->selectRaw("purchase_invoices.cost_type_id AS cost_type_id, {$totals}", $bindings)
            ->groupBy('purchase_invoices.cost_type_id')
            ->get();

        $cents = fn (string $column): array => $rows->mapWithKeys(fn (object $row): array => [(int) $row->cost_type_id => ExposureSupport::cents($row->{$column})])->all();

        return ExposureSupport::costTypeRows($cents('open_total'), $cents('overdue_total'));
    }
}
