<?php

declare(strict_types=1);

namespace App\Services\ManagementControl;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The statement by business function (spec 0268, D-11/D-22/D-23). The figures
 * of a commessa (integer cents) are split among the functions of its REVENUE
 * lines in proportion to their effective revenue, the last share absorbing the
 * rounding so the rows always add up to the commessa. A line without function
 * weighs on "unattributed", and so does a commessa with no revenue. The
 * "unattributed" row is always last.
 */
final class IncomeStatementGrouper
{
    public const string UNATTRIBUTED = 'unattributed';

    /**
     * @param  Collection<int, object>  $workOrders  rows of the income statement query
     * @param  array<int, array<string, int>>  $figures  base figures by work order id
     * @param  array<int, array<int, int>>  $weights  revenue by business function id (0 = none) by work order id
     * @return array<int, array{key: string, work_orders_count: int, base: array<string, int>}> label-less rows in display order
     */
    public function functions(Collection $workOrders, array $figures, array $weights): array
    {
        $rows = [];
        foreach ($workOrders as $workOrder) {
            foreach ($this->shares($figures[$workOrder->id], $weights[$workOrder->id]) as $key => $base) {
                $rows[$key] ??= ['key' => (string) $key, 'work_orders_count' => 0, 'base' => array_fill_keys(WorkOrderFigureSources::BASE_FIGURES, 0)];
                $rows[$key]['work_orders_count']++;
                foreach ($base as $figure => $cents) {
                    $rows[$key]['base'][$figure] += $cents;
                }
            }
        }

        return $this->sorted($rows);
    }

    /**
     * Names of the business functions of the given row lists, by key; one query.
     *
     * @param  array<int, array<int, array{key: string}>>  $rowLists
     * @return array<string, string>
     */
    public function functionNames(array $rowLists): array
    {
        $ids = collect($rowLists)->flatten(1)->pluck('key')->reject(self::UNATTRIBUTED)->unique()->values()->all();

        return DB::table('business_functions')->whereIn('id', $ids)->pluck('name', 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(string) $id => (string) $name])->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>> the rows by label ("unattributed" last), the key standing in for a missing label
     */
    public function sorted(array $rows): array
    {
        $rows = array_values($rows);
        usort($rows, function (array $a, array $b): int {
            $unattributed = ($a['key'] === self::UNATTRIBUTED) <=> ($b['key'] === self::UNATTRIBUTED);

            return $unattributed ?: strcasecmp((string) ($a['label'] ?? $a['key']), (string) ($b['label'] ?? $b['key']));
        });

        return $rows;
    }

    /**
     * Splits the figures of one commessa among its business functions; a
     * function with a line but no revenue is present with zero figures.
     *
     * @param  array<string, int>  $base
     * @param  array<int, int>  $weights
     * @return array<int|string, array<string, int>> base figures by function key
     */
    private function shares(array $base, array $weights): array
    {
        $split = WorkOrderFunctionShares::split($base, $weights);
        if ($split === []) {
            return [self::UNATTRIBUTED => $base] + $this->zeroShares($weights, $base);
        }

        $shares = [];
        foreach ($split as $functionId => $share) {
            $shares[$this->key($functionId)] = $share;
        }

        return $shares + $this->zeroShares($weights, $base);
    }

    /**
     * @param  array<int, int>  $weights
     * @param  array<string, int>  $base
     * @return array<int|string, array<string, int>>
     */
    private function zeroShares(array $weights, array $base): array
    {
        $zero = array_fill_keys(array_keys($base), 0);

        return array_combine(array_map($this->key(...), array_keys($weights)), array_fill(0, count($weights), $zero)) ?: [];
    }

    private function key(int $functionId): string
    {
        return $functionId === 0 ? self::UNATTRIBUTED : (string) $functionId;
    }
}
