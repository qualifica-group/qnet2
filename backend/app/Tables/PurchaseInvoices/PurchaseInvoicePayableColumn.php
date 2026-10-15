<?php

declare(strict_types=1);

namespace App\Tables\PurchaseInvoices;

use App\Models\PurchaseInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * The derived `payable_amount` column (spec 0267, D-6): total minus the
 * withholding and ENASARCO, sorted and filtered on a fixed SQL expression.
 * The operator comes from an allow-list and the values are bound parameters.
 */
final class PurchaseInvoicePayableColumn
{
    public const string COLUMN_ID = 'payable_amount';

    private const string EXPRESSION = 'purchase_invoices.total_amount - purchase_invoices.withholding_amount - purchase_invoices.enasarco_amount';

    private const array OPERATORS = [
        'equals' => '=',
        'notEqual' => '!=',
        'lessThan' => '<',
        'lessThanOrEqual' => '<=',
        'greaterThan' => '>',
        'greaterThanOrEqual' => '>=',
    ];

    /**
     * @param  Builder<PurchaseInvoice>  $query
     * @param  array<string, mixed>  $filter
     */
    public static function applyFilter(Builder $query, string $columnId, array $filter): bool
    {
        if ($columnId !== self::COLUMN_ID) {
            return false;
        }

        $conditions = $filter['conditions'] ?? null;

        if (! is_array($conditions) || $conditions === []) {
            self::applyCondition($query, $filter, 'and');

            return true;
        }

        $boolean = ($filter['operator'] ?? null) === 'OR' ? 'or' : 'and';
        $query->where(function (Builder $nested) use ($conditions, $boolean): void {
            foreach ($conditions as $condition) {
                if (is_array($condition)) {
                    self::applyCondition($nested, $condition, $boolean);
                }
            }
        });

        return true;
    }

    /**
     * @param  Builder<PurchaseInvoice>  $query
     */
    public static function applySort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId !== self::COLUMN_ID) {
            return false;
        }

        $query->orderBy(self::expression(), $direction === 'desc' ? 'desc' : 'asc');

        return true;
    }

    /**
     * @param  Builder<PurchaseInvoice>  $query
     * @param  array<string, mixed>  $condition
     */
    private static function applyCondition(Builder $query, array $condition, string $boolean): void
    {
        $type = is_string($condition['type'] ?? null) ? $condition['type'] : 'equals';
        $value = self::number($condition['filter'] ?? null);

        if ($type === 'inRange') {
            $to = self::number($condition['filterTo'] ?? null);

            if ($value !== null && $to !== null) {
                $query->whereBetween(self::expression(), [$value, $to], $boolean);
            }

            return;
        }

        if ($value !== null) {
            $query->where(self::expression(), self::OPERATORS[$type] ?? '=', $value, $boolean);
        }
    }

    private static function expression(): Expression
    {
        return DB::raw(self::EXPRESSION);
    }

    private static function number(mixed $value): int|float|null
    {
        return is_numeric($value) ? $value + 0 : null;
    }
}
