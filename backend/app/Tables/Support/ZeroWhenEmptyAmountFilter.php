<?php

namespace App\Tables\Support;

use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Number filter on an amount that stays NULL until something is recorded
 * (collected / paid amount of an installment): its condition reads NULL as 0,
 * so "= 0" or "< 100" also finds the installments nothing was recorded on.
 * The Set part of the grid multi filter keeps the raw column, so its blank
 * entry still matches the empty cell.
 */
final class ZeroWhenEmptyAmountFilter
{
    /**
     * @param  Builder<Model>  $query
     * @param  string  $table  the table owning the amount, joined by `{$table}.id`
     * @param  string  $amount  the unqualified amount column of $table (a code constant)
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public static function apply(Builder $query, string $table, string $amount, array $columnConfig, array $filter): void
    {
        $subFilters = ($filter['filterType'] ?? null) === 'multi' && is_array($filter['filterModels'] ?? null)
            ? $filter['filterModels']
            : [$filter];

        foreach ($subFilters as $subFilter) {
            if (! is_array($subFilter) || $subFilter === []) {
                continue;
            }

            ($subFilter['filterType'] ?? null) === 'set'
                ? app(FilterApplier::class)->apply($query, "{$table}.{$amount}", $columnConfig, $subFilter)
                : self::applyCondition($query, $table, $amount, $columnConfig, $subFilter);
        }
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $condition
     */
    private static function applyCondition(Builder $query, string $table, string $amount, array $columnConfig, array $condition): void
    {
        $derived = DB::table($table)
            ->select("{$table}.id")
            ->selectRaw("COALESCE({$table}.{$amount}, 0) as amount_value");

        $matching = $query->getModel()->newQuery()->fromSub($derived, 'derived')->select('derived.id');
        app(FilterApplier::class)->apply($matching, 'derived.amount_value', $columnConfig, $condition);

        $query->whereIn("{$table}.id", $matching);
    }
}
