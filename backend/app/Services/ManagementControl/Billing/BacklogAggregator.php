<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Billing;

use App\DataObjects\ManagementControl\BillingFilters;
use App\Services\ManagementControl\Exposure\AgingSql;
use App\Services\ManagementControl\Exposure\ExposureSupport;
use App\Services\ManagementControl\FlowSupport;
use App\Services\ManagementControl\IncomeStatementGrouper;
use App\Services\ManagementControl\WorkOrderFigureSources;
use App\Services\ManagementControl\WorkOrderReferenceQuote;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What is left to invoice on the open commesse (spec 0271, D-1/D-2/D-3): the
 * contract revenue of the assigned lines minus the net of the invoices, a
 * snapshot at today. It can be negative (over-invoiced commessa). The figures
 * come from the same sources as the income statement; amounts are integer
 * cents and leave as numbers with two decimals.
 */
final class BacklogAggregator
{
    /** Age bucket => days (exclusive upper limit) of the commessa since its start date; null = unbounded. */
    private const array AGE_BUCKETS = ['0_3' => 90, '3_6' => 180, '6_12' => 365, 'over_12' => null];

    private const array SCOPE_COLUMNS = [
        'company' => 'quotes.company_id',
        'company_site' => 'quotes.company_site_id',
        'operational_site' => 'quotes.operational_site_id',
    ];

    public function __construct(
        private readonly WorkOrderFigureSources $sources,
        private readonly IncomeStatementGrouper $grouper,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(BillingFilters $filters, string $today): array
    {
        // Step 1: the open commesse with their dimensions and age bucket
        $workOrders = $this->workOrders($filters, $today);

        // Step 2: their contract and invoiced figures, one aggregate query per source
        $collected = $this->sources->collect($workOrders->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());
        $contract = (int) collect($collected['figures'])->sum('revenue_contract');
        $invoiced = (int) collect($collected['figures'])->sum('revenue_invoiced');

        return [
            'work_orders_count' => $workOrders->count(),
            'contract' => ExposureSupport::euros($contract),
            'invoiced' => ExposureSupport::euros($invoiced),
            'to_invoice' => ExposureSupport::euros($contract - $invoiced),
            // Step 3: the same figures by dimension and by age
            'by_dimension' => $this->byDimension($filters->groupBy, $workOrders, $collected),
            'by_age' => $this->byAge($workOrders, $collected['figures']),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private function workOrders(BillingFilters $filters, string $today): Collection
    {
        $days = AgingSql::daysLate('work_orders.start_date');
        $cases = [];
        foreach (self::AGE_BUCKETS as $bucket => $limit) {
            $cases[] = $limit === null ? "ELSE '{$bucket}'" : "WHEN {$days} < {$limit} THEN '{$bucket}'";
        }
        $bindings = array_fill(0, count(self::AGE_BUCKETS) - 1, $today);

        $query = WorkOrderReferenceQuote::join(DB::table('work_orders'))
            ->leftJoin('companies', 'companies.id', '=', 'quotes.company_id')
            ->leftJoin('referents', 'referents.id', '=', 'quotes.commercial_id')
            ->where('work_orders.is_force_closed', false)
            ->selectRaw(
                'work_orders.id, quotes.company_id, companies.denomination AS company_name, quotes.commercial_id, referents.name AS commercial_name, CASE '
                .implode(' ', $cases).' END AS age_bucket',
                $bindings,
            );

        return FlowSupport::scope($query, $filters->companyIds, $filters->companySiteIds, $filters->operationalSiteIds, self::SCOPE_COLUMNS)->get();
    }

    /**
     * @param  Collection<int, object>  $workOrders
     * @param  array{figures: array<int, array<string, int>>, weights: array<int, array<int, int>>}  $collected
     * @return array<int, array<string, mixed>>
     */
    private function byDimension(string $groupBy, Collection $workOrders, array $collected): array
    {
        return match ($groupBy) {
            BillingFilters::GROUP_COMPANY => $this->bySimpleDimension($workOrders, $collected['figures'], 'company_id', 'company_name'),
            BillingFilters::GROUP_COMMERCIAL => $this->bySimpleDimension($workOrders, $collected['figures'], 'commercial_id', 'commercial_name'),
            default => $this->byFunction($workOrders, $collected),
        };
    }

    /**
     * The figures of each commessa split among its business functions (spec 0268, D-11).
     *
     * @param  Collection<int, object>  $workOrders
     * @param  array{figures: array<int, array<string, int>>, weights: array<int, array<int, int>>}  $collected
     * @return array<int, array<string, mixed>>
     */
    private function byFunction(Collection $workOrders, array $collected): array
    {
        $functions = $this->grouper->functions($workOrders, $collected['figures'], $collected['weights']);
        $names = $this->grouper->functionNames([$functions]);

        $rows = array_map(fn (array $function): array => $this->row(
            $function['key'],
            $function['key'] === IncomeStatementGrouper::UNATTRIBUTED ? null : ($names[$function['key']] ?? $function['key']),
            $function['work_orders_count'],
            $function['base']['revenue_contract'],
            $function['base']['revenue_invoiced'],
        ), $functions);

        return $this->grouper->sorted($rows);
    }

    /**
     * Company or commercial of the reference offer; a commessa without one is "unattributed".
     *
     * @param  Collection<int, object>  $workOrders
     * @param  array<int, array<string, int>>  $figures
     * @return array<int, array<string, mixed>>
     */
    private function bySimpleDimension(Collection $workOrders, array $figures, string $idColumn, string $nameColumn): array
    {
        $rows = [];
        foreach ($workOrders->groupBy(fn (object $workOrder): string => $workOrder->{$idColumn} === null ? IncomeStatementGrouper::UNATTRIBUTED : (string) $workOrder->{$idColumn}) as $key => $members) {
            $label = $key === IncomeStatementGrouper::UNATTRIBUTED ? null : (string) ($members->first()->{$nameColumn} ?? $key);
            $rows[] = $this->row(
                (string) $key,
                $label,
                $members->count(),
                (int) $members->sum(fn (object $workOrder): int => $figures[$workOrder->id]['revenue_contract']),
                (int) $members->sum(fn (object $workOrder): int => $figures[$workOrder->id]['revenue_invoiced']),
            );
        }

        return $this->grouper->sorted($rows);
    }

    /**
     * Always one row per age bucket, in order.
     *
     * @param  Collection<int, object>  $workOrders
     * @param  array<int, array<string, int>>  $figures
     * @return array<int, array{bucket: string, work_orders_count: int, to_invoice: float}>
     */
    private function byAge(Collection $workOrders, array $figures): array
    {
        $byBucket = $workOrders->groupBy('age_bucket');

        return array_map(function (string $bucket) use ($byBucket, $figures): array {
            $members = $byBucket->get($bucket, collect());

            return [
                'bucket' => $bucket,
                'work_orders_count' => $members->count(),
                'to_invoice' => ExposureSupport::euros((int) $members->sum(
                    fn (object $workOrder): int => $figures[$workOrder->id]['revenue_contract'] - $figures[$workOrder->id]['revenue_invoiced'],
                )),
            ];
        }, array_keys(self::AGE_BUCKETS));
    }

    /**
     * @return array{key: string, label: string|null, work_orders_count: int, contract: float, invoiced: float, to_invoice: float}
     */
    private function row(string $key, ?string $label, int $count, int $contract, int $invoiced): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'work_orders_count' => $count,
            'contract' => ExposureSupport::euros($contract),
            'invoiced' => ExposureSupport::euros($invoiced),
            'to_invoice' => ExposureSupport::euros($contract - $invoiced),
        ];
    }
}
