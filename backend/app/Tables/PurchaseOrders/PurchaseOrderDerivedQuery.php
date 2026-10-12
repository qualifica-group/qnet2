<?php

namespace App\Tables\PurchaseOrders;

use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Purchase-order grid logic that the generic related-column helpers
 * (PurchaseRequestDerivedQuery) do not cover. Table and column names are
 * constants; user input only reaches the query as a bound parameter.
 */
final class PurchaseOrderDerivedQuery
{
    /**
     * Quick search on the public code: "n/year" matches that exact order, a
     * bare number matches the number. Anything else matches nothing here.
     *
     * @param  Builder<Model>  $query
     */
    public static function searchCode(Builder $query, string $pattern): void
    {
        $term = trim(stripslashes(trim($pattern, '%')));

        if (preg_match('#^(\d{1,9})\s*/\s*(\d{4})$#', $term, $parts) === 1) {
            $query->orWhere(static fn (Builder $code) => $code->where('purchase_orders.number', (int) $parts[1])->where('purchase_orders.year', (int) $parts[2]));
        } elseif (preg_match('/^\d{1,9}$/', $term) === 1) {
            $query->orWhere('purchase_orders.number', (int) $term);
        }
    }

    /**
     * ORDER BY the public code: year, then number.
     *
     * @param  Builder<Model>  $query
     */
    public static function orderByCode(Builder $query, string $direction): void
    {
        $query->orderBy('purchase_orders.year', $direction)->orderBy('purchase_orders.number', $direction);
    }

    /**
     * Distinct sector names of the orders in `$query` (`/values`; the pivot is
     * not a BelongsTo chain, which distinctRelated() walks).
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    public static function distinctSectors(Builder $query, ?string $search, int $limit): array
    {
        $orderIds = (clone $query)->reorder()->setEagerLoads([])->select('purchase_orders.id')->toBase();
        $sectorIds = DB::table('purchase_order_sector')->whereIn('purchase_order_id', $orderIds)->select('sector_id');

        return DB::table('sectors')
            ->whereIn('id', $sectorIds)
            ->when($search !== null && $search !== '', static function ($sectors) use ($search): void {
                $sectors->where('name', 'like', '%'.app(FilterApplier::class)->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * Sums of the money columns over the orders in `$query`, as 2-decimal strings.
     *
     * @param  Builder<Model>  $query
     * @return array{taxable_total: string, vat_total: string, grand_total: string}
     */
    public static function totals(Builder $query): array
    {
        $ids = (clone $query)->reorder()->setEagerLoads([])->select('purchase_orders.id')->toBase();
        $sums = DB::table('purchase_orders')->whereIn('id', $ids)
            ->selectRaw('COALESCE(SUM(taxable_total), 0) as taxable_total, COALESCE(SUM(vat_total), 0) as vat_total, COALESCE(SUM(grand_total), 0) as grand_total')
            ->first();

        return [
            'taxable_total' => number_format((float) $sums->taxable_total, 2, '.', ''),
            'vat_total' => number_format((float) $sums->vat_total, 2, '.', ''),
            'grand_total' => number_format((float) $sums->grand_total, 2, '.', ''),
        ];
    }
}
