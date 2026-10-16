<?php

declare(strict_types=1);

namespace App\Services\ManagementControl;

use App\DataObjects\ManagementControl\StructureCostsFilters;
use App\Models\CostType;
use App\Services\Purchasing\CommittedOrderLineQuery;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The structure costs (spec 0268, D-1/D-15): the passive cycle not tied to any
 * commessa, by cost type and by company / company site / operational site.
 * Committed = order lines without commessa on invoiceable orders, net of what
 * documents bill; invoiced = document lines without commessa, leaving out the
 * invoices of commission statements (D-9, already in the commessa margins).
 * A constant number of aggregate queries; amounts are integer cents summed,
 * numbers with two decimals out.
 */
final class StructureCostsBuilder
{
    public const string UNATTRIBUTED = 'unattributed';

    /** Header column filtered/grouped on both document tables, with the table and label column of its dimension. */
    private const array DIMENSIONS = [
        StructureCostsFilters::GROUP_COMPANY => ['company_id', 'companies', 'denomination'],
        StructureCostsFilters::GROUP_COMPANY_SITE => ['company_site_id', 'company_sites', 'name'],
        StructureCostsFilters::GROUP_OPERATIONAL_SITE => ['operational_site_id', 'operational_sites', 'alias'],
    ];

    public function __construct(private readonly CommittedOrderLineQuery $committed) {}

    /**
     * @return array{totals: array<string, float>, by_cost_type: array<int, array<string, mixed>>, by_dimension: array<int, array<string, mixed>>}
     */
    public function handle(StructureCostsFilters $filters): array
    {
        // Step 1: the two sources, by cost type and by the requested dimension
        $column = self::DIMENSIONS[$filters->groupBy][0];
        $byType = [
            'committed' => $this->committed->committedCentsBy($this->committedLines($filters), 'purchase_order_lines.cost_type_id'),
            'invoiced' => $this->committed->centsBy($this->invoicedLines($filters), 'purchase_invoice_lines.net_amount', 'purchase_invoice_lines.cost_type_id'),
        ];
        $byDimension = [
            'committed' => $this->committed->committedCentsBy($this->committedLines($filters), "purchase_orders.{$column}"),
            'invoiced' => $this->committed->centsBy($this->invoicedLines($filters), 'purchase_invoice_lines.net_amount', "purchase_invoices.{$column}"),
        ];

        // Step 2: labelled rows in display order
        return [
            'totals' => $this->figures(array_sum($byType['committed']), array_sum($byType['invoiced'])),
            'by_cost_type' => $this->costTypeRows($byType),
            'by_dimension' => $this->dimensionRows($byDimension, $filters->groupBy),
        ];
    }

    private function committedLines(StructureCostsFilters $filters): Builder
    {
        return $this->filtered($this->committed->withoutWorkOrder(), 'purchase_orders', 'order_date', $filters);
    }

    private function invoicedLines(StructureCostsFilters $filters): Builder
    {
        $statementInvoices = DB::table('commission_settlements')->whereNotNull('purchase_invoice_id')->select('purchase_invoice_id');
        $query = DB::table('purchase_invoice_lines')
            ->join('purchase_invoices', 'purchase_invoices.id', '=', 'purchase_invoice_lines.purchase_invoice_id')
            ->whereNull('purchase_invoice_lines.work_order_id')
            ->whereNotIn('purchase_invoices.id', $statementInvoices);

        return $this->filtered($query, 'purchase_invoices', 'document_date', $filters);
    }

    private function filtered(Builder $query, string $table, string $dateColumn, StructureCostsFilters $filters): Builder
    {
        return $query
            ->when($filters->dateFrom !== null, fn (Builder $q) => $q->where("{$table}.{$dateColumn}", '>=', $filters->dateFrom))
            ->when($filters->dateTo !== null, fn (Builder $q) => $q->where("{$table}.{$dateColumn}", '<=', $filters->dateTo))
            ->when($filters->companyIds !== [], fn (Builder $q) => $q->whereIn("{$table}.company_id", $filters->companyIds))
            ->when($filters->companySiteIds !== [], fn (Builder $q) => $q->whereIn("{$table}.company_site_id", $filters->companySiteIds))
            ->when($filters->operationalSiteIds !== [], fn (Builder $q) => $q->whereIn("{$table}.operational_site_id", $filters->operationalSiteIds));
    }

    /**
     * @param  array{committed: array<int, int>, invoiced: array<int, int>}  $sources
     * @return array<int, array<string, mixed>>
     */
    private function costTypeRows(array $sources): array
    {
        $ids = array_values(array_unique([...array_keys($sources['committed']), ...array_keys($sources['invoiced'])]));
        $rows = CostType::query()->whereIn('id', $ids)->orderBy('sort_order')->orderBy('name')->orderBy('id')->get()
            ->map(fn (CostType $type): array => [
                'cost_type' => ['id' => $type->id, 'name' => $type->name, 'color' => $type->color, 'nature' => $type->nature?->value],
                ...$this->figures($sources['committed'][$type->id] ?? 0, $sources['invoiced'][$type->id] ?? 0),
            ])->all();

        if (in_array(0, $ids, true)) {
            $rows[] = ['cost_type' => null, ...$this->figures($sources['committed'][0] ?? 0, $sources['invoiced'][0] ?? 0)];
        }

        return $rows;
    }

    /**
     * @param  array{committed: array<int, int>, invoiced: array<int, int>}  $sources
     * @return array<int, array<string, mixed>>
     */
    private function dimensionRows(array $sources, string $groupBy): array
    {
        [, $table, $labelColumn] = self::DIMENSIONS[$groupBy];
        $ids = array_values(array_filter(array_unique([...array_keys($sources['committed']), ...array_keys($sources['invoiced'])])));
        $labels = DB::table($table)->whereIn('id', $ids)->pluck($labelColumn, 'id');

        $rows = array_map(fn (int $id): array => [
            'key' => (string) $id,
            'label' => (string) ($labels[$id] ?? $id),
            ...$this->figures($sources['committed'][$id] ?? 0, $sources['invoiced'][$id] ?? 0),
        ], $ids);
        usort($rows, fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        if (isset($sources['committed'][0]) || isset($sources['invoiced'][0])) {
            $rows[] = ['key' => self::UNATTRIBUTED, 'label' => null, ...$this->figures($sources['committed'][0] ?? 0, $sources['invoiced'][0] ?? 0)];
        }

        return $rows;
    }

    /**
     * @return array{committed_net: float, invoiced_net: float, total_net: float}
     */
    private function figures(int $committedCents, int $invoicedCents): array
    {
        return [
            'committed_net' => round($committedCents / 100, 2),
            'invoiced_net' => round($invoicedCents / 100, 2),
            'total_net' => round(($committedCents + $invoicedCents) / 100, 2),
        ];
    }
}
