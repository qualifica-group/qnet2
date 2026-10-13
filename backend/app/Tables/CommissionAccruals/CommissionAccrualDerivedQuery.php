<?php

namespace App\Tables\CommissionAccruals;

use App\Enums\CommissionAccrualType;
use App\Models\CommissionAccrual;
use App\Services\Table\FilterApplier;
use App\Tables\Support\SetExpressionQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filters, sort and distinct values of the `commission-accruals` columns
 * (spec 0212, 0213). The joins repeat plain column names, so every column is
 * qualified through CommissionAccrualSql; values are validated against an
 * allow-list or bound as parameters, never concatenated.
 */
final class CommissionAccrualDerivedQuery
{
    /** Columns whose set filter / distinct values are a column or an SQL expression. */
    private const array SET_COLUMNS = ['accrued_month', 'recipient', 'recipient_kind', 'recipient_role', 'type', 'company', 'customer', 'status'];

    /** Text columns that only exist as an SQL expression ("n/year" labels). */
    private const array LABEL_COLUMNS = ['invoice', 'settlement'];

    /**
     * Handles every filterable column of the domain; false for an unknown one.
     *
     * @param  Builder<CommissionAccrual>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public static function applyFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if (in_array($columnId, self::SET_COLUMNS, true)) {
            SetExpressionQuery::apply($query, (string) CommissionAccrualSql::label($columnId), $filter);

            return true;
        }

        if (in_array($columnId, self::LABEL_COLUMNS, true)) {
            self::applyLabel($query, (string) CommissionAccrualSql::label($columnId), $columnConfig, $filter);

            return true;
        }

        if (! isset(CommissionAccrualSql::PLAIN_COLUMNS[$columnId])) {
            return false;
        }

        app(FilterApplier::class)->apply($query, CommissionAccrualSql::PLAIN_COLUMNS[$columnId], $columnConfig, $filter);

        return true;
    }

    /**
     * @param  Builder<CommissionAccrual>  $query
     */
    public static function applySort(Builder $query, string $columnId, string $direction): bool
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';
        $expression = match ($columnId) {
            'recipient' => CommissionAccrualSql::recipientName(),
            'invoice' => CommissionAccrualSql::invoiceLabel(),
            'settlement' => CommissionAccrualSql::settlementLabel(),
            default => null,
        };

        if ($expression !== null) {
            $query->orderByRaw("{$expression} {$direction}");

            return true;
        }

        if (! isset(CommissionAccrualSql::PLAIN_COLUMNS[$columnId])) {
            return false;
        }

        $query->orderBy(CommissionAccrualSql::PLAIN_COLUMNS[$columnId], $direction);

        return true;
    }

    /**
     * Distinct values of the set columns; null for any other column.
     *
     * @param  Builder<CommissionAccrual>  $query
     * @return array<int, string|null>|null
     */
    public static function distinctValues(string $columnId, ?string $search, Builder $query, int $limit): ?array
    {
        $fixed = match ($columnId) {
            'status' => CommissionAccrualSql::STATUSES,
            'recipient_kind' => CommissionAccrualColumnCatalog::RECIPIENT_KINDS,
            'recipient_role' => CommissionAccrualColumnCatalog::roles(),
            'type' => CommissionAccrualType::values(),
            default => null,
        };

        if ($fixed !== null) {
            return $fixed;
        }

        if (! in_array($columnId, self::SET_COLUMNS, true)) {
            return null;
        }

        return SetExpressionQuery::distinct($query, (string) CommissionAccrualSql::label($columnId), $search, $limit);
    }

    /**
     * An "n/year" label only exists as an expression, so the text filter
     * runs on a derived table and the movements are matched by id.
     *
     * @param  Builder<CommissionAccrual>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    private static function applyLabel(Builder $query, string $expression, array $columnConfig, array $filter): void
    {
        $derived = CommissionAccrualSql::withJoins(CommissionAccrual::query())
            ->select('commission_accruals.id')
            ->selectRaw("{$expression} as label");

        $matching = CommissionAccrual::query()->fromSub($derived, 'derived')->select('derived.id');
        app(FilterApplier::class)->apply($matching, 'derived.label', $columnConfig, $filter);

        $query->whereIn('commission_accruals.id', $matching);
    }
}
