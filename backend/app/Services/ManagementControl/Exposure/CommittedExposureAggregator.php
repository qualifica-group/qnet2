<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Exposure;

use App\DataObjects\ManagementControl\ExposureFilters;
use App\Services\Purchasing\CommittedOrderLineQuery;
use Illuminate\Database\Query\Builder;

/**
 * The open commitments (spec 0269, D-8): purchase order lines of invoiceable
 * orders not yet billed, a snapshot at today filtered by the order header.
 * The document type does not apply: an order is not an invoicing document.
 */
final class CommittedExposureAggregator
{
    private const string COST_TYPE_COLUMN = 'purchase_order_lines.cost_type_id';

    private const string SUPPLIER_COLUMN = 'purchase_orders.supplier_id';

    public function __construct(private readonly CommittedOrderLineQuery $lines) {}

    /**
     * @return array<string, mixed>
     */
    public function section(ExposureFilters $filters): array
    {
        // Step 1: committed by cost type, in total and without commessa
        $byType = $this->lines->committedCentsBy($this->scoped($this->lines->lines(), $filters), self::COST_TYPE_COLUMN);
        $withoutWorkOrder = array_sum($this->lines->committedCentsBy($this->scoped($this->lines->withoutWorkOrder(), $filters), self::COST_TYPE_COLUMN));
        $total = array_sum($byType);

        // Step 2: the suppliers committing the most
        $suppliers = $this->lines->topCommittedBy($this->scoped($this->lines->lines(), $filters), self::SUPPLIER_COLUMN, $filters->top);
        $names = ExposureSupport::registries(array_column($suppliers, 'id'));

        return [
            'total' => ExposureSupport::euros($total),
            'with_work_order' => ExposureSupport::euros($total - $withoutWorkOrder),
            'without_work_order' => ExposureSupport::euros($withoutWorkOrder),
            'by_supplier' => array_map(fn (array $supplier): array => [
                'registry' => $names[$supplier['id']] ?? ['id' => $supplier['id'], 'name' => (string) $supplier['id']],
                'committed' => ExposureSupport::euros($supplier['cents']),
                'orders' => $supplier['orders'],
            ], $suppliers),
            'by_cost_type' => ExposureSupport::costTypeRows($byType, []),
        ];
    }

    /**
     * Committed cents by dimension id (0 = no value).
     *
     * @return array<int, int>
     */
    public function centsBy(ExposureFilters $filters): array
    {
        return $this->lines->committedCentsBy($this->scoped($this->lines->lines(), $filters), 'purchase_orders.'.$filters->groupBy.'_id');
    }

    private function scoped(Builder $query, ExposureFilters $filters): Builder
    {
        return $query
            ->when($filters->companyIds !== [], fn (Builder $q) => $q->whereIn('purchase_orders.company_id', $filters->companyIds))
            ->when($filters->companySiteIds !== [], fn (Builder $q) => $q->whereIn('purchase_orders.company_site_id', $filters->companySiteIds))
            ->when($filters->operationalSiteIds !== [], fn (Builder $q) => $q->whereIn('purchase_orders.operational_site_id', $filters->operationalSiteIds));
    }
}
