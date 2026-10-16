<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Exposure;

use App\DataObjects\ManagementControl\ExposureFilters;

/**
 * The concentration of the customers (spec 0269, D-10): the share of the
 * invoiced amount of the period (total of the document) and of the overdue at
 * today held by the first customers by invoiced amount.
 */
final class CustomerConcentrationAggregator
{
    public function __construct(private readonly ReceivableScheduleAggregator $receivable) {}

    /**
     * @param  int  $overdueTotal  cents of overdue receivable at today, whatever the customer
     * @return array<string, mixed>
     */
    public function section(ExposureFilters $filters, string $today, int $overdueTotal): array
    {
        // Step 1: the invoiced amount of the period, in total and for the first customers
        $invoicedTotal = $this->invoicedTotal($filters);
        $top = $this->topInvoiced($filters);

        // Step 2: the overdue of those customers
        $overdue = $this->overdueOf(array_keys($top), $filters, $today);
        $names = ExposureSupport::registries(array_keys($top));

        $rows = [];
        foreach ($top as $registryId => $invoiced) {
            $rows[] = [
                'registry' => $names[$registryId] ?? ['id' => $registryId, 'name' => (string) $registryId],
                'invoiced' => ExposureSupport::euros($invoiced),
                'invoiced_share' => ExposureSupport::share($invoiced, $invoicedTotal),
                'overdue' => ExposureSupport::euros($overdue[$registryId] ?? 0),
                'overdue_share' => ExposureSupport::share($overdue[$registryId] ?? 0, $overdueTotal),
            ];
        }

        return [
            'invoiced_total' => ExposureSupport::euros($invoicedTotal),
            'overdue_total' => ExposureSupport::euros($overdueTotal),
            'top_invoiced_share' => ExposureSupport::share(array_sum($top), $invoicedTotal),
            'top_overdue_share' => ExposureSupport::share(array_sum($overdue), $overdueTotal),
            'rows' => $rows,
        ];
    }

    private function invoicedTotal(ExposureFilters $filters): int
    {
        $columns = $this->receivable->columns();
        $row = $this->receivable->documentsInPeriod($filters)->selectRaw("COALESCE(SUM({$columns['invoiced']}), 0) AS total")->first();

        return ExposureSupport::cents($row->total);
    }

    /**
     * @return array<int, int> invoiced cents by customer, largest first
     */
    private function topInvoiced(ExposureFilters $filters): array
    {
        $columns = $this->receivable->columns();

        return $this->receivable->documentsInPeriod($filters)
            ->selectRaw("{$columns['registry']} AS registry_id, SUM({$columns['invoiced']}) AS invoiced")
            ->groupBy($columns['registry'])
            ->orderByDesc('invoiced')->orderBy('registry_id')
            ->limit($filters->top)
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->registry_id => ExposureSupport::cents($row->invoiced)])
            ->all();
    }

    /**
     * @param  array<int, int>  $registryIds
     * @return array<int, int> overdue cents by customer
     */
    private function overdueOf(array $registryIds, ExposureFilters $filters, string $today): array
    {
        if ($registryIds === []) {
            return [];
        }

        $columns = $this->receivable->columns();
        [$totals, $bindings] = AgingSql::totals($columns['due'], $columns['residual'], $today);

        return $this->receivable->scopedInstallments($filters)
            ->whereIn($columns['registry'], $registryIds)
            ->selectRaw("{$columns['registry']} AS registry_id, {$totals}", $bindings)
            ->groupBy($columns['registry'])
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->registry_id => ExposureSupport::cents($row->overdue_total)])
            ->all();
    }
}
