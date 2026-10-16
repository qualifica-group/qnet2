<?php

declare(strict_types=1);

namespace App\Services\ManagementControl;

use App\Enums\QuoteLineType;
use App\Models\QuoteLine;
use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\WorkOrders\LineEffectiveRevenue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The business functions of a commessa and their weight (spec 0268, D-11, shared
 * with spec 0270): the effective contract revenue (integer cents) of its assigned
 * REVENUE lines by effective business function of the product category (0 = no
 * function). The figures of a commessa are split among the functions in
 * proportion to these weights.
 */
final class WorkOrderFunctionShares
{
    public function __construct(
        private readonly LineEffectiveRevenue $effectiveRevenue,
        private readonly CategoryHierarchy $categories,
    ) {}

    /**
     * The weights of the given commesse: two lookups plus the category walk, whatever their number.
     *
     * @param  array<int, int>  $workOrderIds
     * @return array<int, array<int, int>> weights by business function id by work order id
     */
    public function forWorkOrders(array $workOrderIds): array
    {
        if ($workOrderIds === []) {
            return [];
        }

        $pairs = DB::table('quote_line_work_order')->whereIn('work_order_id', $workOrderIds)->get(['work_order_id', 'quote_line_id']);
        $lines = QuoteLine::query()
            ->whereIn('id', $pairs->pluck('quote_line_id')->unique()->values()->all())
            ->where('line_type', QuoteLineType::Revenue)
            ->with(['commissions', 'product'])
            ->get()
            ->keyBy('id');
        $pairsByOrder = $pairs->groupBy('work_order_id');
        $categoryFunctions = $this->categories->effectiveBusinessFunctionSummaries();

        $weights = [];
        foreach ($workOrderIds as $id) {
            $ownLines = ($pairsByOrder->get($id) ?? collect())->map(fn (object $pair): ?QuoteLine => $lines->get($pair->quote_line_id))->filter();
            $weights[$id] = $this->fromLines($ownLines, $categoryFunctions);
        }

        return $weights;
    }

    /**
     * @param  Collection<int, QuoteLine>  $lines  REVENUE lines with `commissions` and `product` loaded
     * @param  array<int, array{id: int, name: string}|null>  $categoryFunctions  effective function by category id (spec 0040)
     * @return array<int, int> cents by business function id
     */
    public function fromLines(Collection $lines, array $categoryFunctions): array
    {
        $weights = [];
        foreach ($lines as $line) {
            $functionId = $categoryFunctions[$line->product?->category_id]['id'] ?? 0;
            $weights[$functionId] = ($weights[$functionId] ?? 0) + $this->effectiveRevenue->cents($line);
        }

        return $weights;
    }

    /**
     * Splits the figures among the functions with a positive weight, in proportion
     * to it; the last share (function 0 always last) absorbs the rounding so the
     * shares add up to the figures. No positive weight: no shares.
     *
     * @param  array<string, int>  $base  cents by figure name
     * @param  array<int, int>  $weights  by business function id (0 = none)
     * @return array<int, array<string, int>> the figures of each function with a positive weight
     */
    public static function split(array $base, array $weights): array
    {
        $positive = array_filter($weights, fn (int $weight): bool => $weight > 0);
        $total = array_sum($positive);
        if ($total <= 0) {
            return [];
        }

        $keys = array_keys($positive);
        usort($keys, fn (int $a, int $b): int => ($a === 0) <=> ($b === 0) ?: $a <=> $b);
        $remaining = $base;
        $shares = [];
        foreach ($keys as $position => $functionId) {
            $isLast = $position === array_key_last($keys);
            $share = [];
            foreach ($base as $figure => $cents) {
                $share[$figure] = $isLast ? $remaining[$figure] : (int) round($cents * $positive[$functionId] / $total);
                $remaining[$figure] -= $share[$figure];
            }
            $shares[$functionId] = $share;
        }

        return $shares;
    }
}
