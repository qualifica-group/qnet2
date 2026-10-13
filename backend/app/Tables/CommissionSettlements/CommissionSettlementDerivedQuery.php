<?php

namespace App\Tables\CommissionSettlements;

use App\Enums\CommissionSettlementStatus;
use App\Models\CommissionSettlement;
use App\Services\Table\FilterApplier;
use App\Tables\Support\SetExpressionQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filters, sort and distinct values of the `commission-settlements` columns
 * (spec 0213). The joins repeat plain column names, so every column is
 * qualified through CommissionSettlementSql; values are validated against an
 * allow-list or bound as parameters, never concatenated.
 */
final class CommissionSettlementDerivedQuery
{
    /** Columns whose set filter / distinct values are a column or an SQL expression. */
    private const array SET_COLUMNS = ['status', 'referent', 'company', 'period_month'];

    /**
     * Handles every filterable column of the domain; false for an unknown one.
     *
     * @param  Builder<CommissionSettlement>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public static function applyFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if (in_array($columnId, self::SET_COLUMNS, true)) {
            SetExpressionQuery::apply($query, (string) CommissionSettlementSql::label($columnId), $filter);

            return true;
        }

        if ($columnId === 'code') {
            self::applyCode($query, $columnConfig, $filter);

            return true;
        }

        if (! isset(CommissionSettlementSql::PLAIN_COLUMNS[$columnId])) {
            return false;
        }

        app(FilterApplier::class)->apply($query, CommissionSettlementSql::PLAIN_COLUMNS[$columnId], $columnConfig, $filter);

        return true;
    }

    /**
     * The code sorts by year then number, not as a string: 10/2026 follows 9/2026.
     *
     * @param  Builder<CommissionSettlement>  $query
     */
    public static function applySort(Builder $query, string $columnId, string $direction): bool
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        if ($columnId === 'code') {
            $query->orderBy('commission_settlements.year', $direction)->orderBy('commission_settlements.number', $direction);

            return true;
        }

        if (! isset(CommissionSettlementSql::PLAIN_COLUMNS[$columnId])) {
            return false;
        }

        $query->orderBy(CommissionSettlementSql::PLAIN_COLUMNS[$columnId], $direction);

        return true;
    }

    /**
     * Distinct values of the set columns; null for any other column.
     *
     * @param  Builder<CommissionSettlement>  $query
     * @return array<int, string|null>|null
     */
    public static function distinctValues(string $columnId, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId === 'status') {
            return CommissionSettlementStatus::values();
        }

        if (! in_array($columnId, self::SET_COLUMNS, true)) {
            return null;
        }

        return SetExpressionQuery::distinct($query, (string) CommissionSettlementSql::label($columnId), $search, $limit);
    }

    /**
     * The "number/year" code only exists as an expression, so the text filter
     * runs on a derived table and the statements are matched by id.
     *
     * @param  Builder<CommissionSettlement>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    private static function applyCode(Builder $query, array $columnConfig, array $filter): void
    {
        $derived = CommissionSettlement::query()
            ->select('commission_settlements.id')
            ->selectRaw(CommissionSettlementSql::code().' as code');

        $matching = CommissionSettlement::query()->fromSub($derived, 'derived')->select('derived.id');
        app(FilterApplier::class)->apply($matching, 'derived.code', $columnConfig, $filter);

        $query->whereIn('commission_settlements.id', $matching);
    }
}
