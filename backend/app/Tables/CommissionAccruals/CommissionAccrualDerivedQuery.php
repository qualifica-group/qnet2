<?php

namespace App\Tables\CommissionAccruals;

use App\Enums\CommissionAccrualType;
use App\Models\CommissionAccrual;
use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Filters, sort and distinct values of the `commission-accruals` columns
 * (spec 0212). The joins repeat plain column names, so every column is
 * qualified through CommissionAccrualSql; values are validated against an
 * allow-list or bound as parameters, never concatenated.
 */
final class CommissionAccrualDerivedQuery
{
    private const int MAX_VALUES = 200;

    /** Columns whose set filter / distinct values are a column or an SQL expression. */
    private const array SET_COLUMNS = ['accrued_month', 'recipient', 'recipient_kind', 'recipient_role', 'type', 'customer', 'status'];

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
            self::applySet($query, (string) CommissionAccrualSql::label($columnId), $filter);

            return true;
        }

        if ($columnId === 'invoice') {
            self::applyInvoice($query, $columnConfig, $filter);

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

        $expression = (string) CommissionAccrualSql::label($columnId);
        $hasSearch = $search !== null && $search !== '';

        $values = self::plain($query)
            ->selectRaw("{$expression} as value")
            ->whereRaw("{$expression} IS NOT NULL")
            ->when($hasSearch, fn (QueryBuilder $builder) => $builder->whereRaw("{$expression} LIKE ?", ['%'.app(FilterApplier::class)->escapeLike((string) $search).'%']))
            ->distinct()
            ->orderBy('value')
            ->limit($limit)
            ->pluck('value')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();

        // The blank entry ("(Vuoti)") when some rows have no value; it matches no search term.
        if (! $hasSearch && self::plain($query)->selectRaw('1')->whereRaw("{$expression} IS NULL")->exists()) {
            array_unshift($values, null);
        }

        return $values;
    }

    /**
     * @param  Builder<CommissionAccrual>  $query
     */
    private static function plain(Builder $query): QueryBuilder
    {
        return (clone $query)->reorder()->toBase()->select([]);
    }

    /**
     * @param  Builder<CommissionAccrual>  $query
     * @param  array<string, mixed>  $filter
     */
    private static function applySet(Builder $query, string $expression, array $filter): void
    {
        $raw = is_array($filter['values'] ?? null) ? $filter['values'] : [];
        $values = array_slice(array_values(array_filter($raw, static fn (mixed $value): bool => is_string($value) || is_int($value))), 0, self::MAX_VALUES);
        $matchesBlank = in_array(null, $raw, true);

        if ($values === [] && ! $matchesBlank) {
            return;
        }

        $column = DB::raw($expression);

        $query->where(static function (Builder $group) use ($column, $values, $matchesBlank): void {
            if ($values !== []) {
                $group->whereIn($column, array_map('strval', $values));
            }

            if ($matchesBlank) {
                $group->orWhereNull($column);
            }
        });
    }

    /**
     * The "number/year" label only exists as an expression, so the text
     * filter runs on a derived table and the movements are matched by id.
     *
     * @param  Builder<CommissionAccrual>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    private static function applyInvoice(Builder $query, array $columnConfig, array $filter): void
    {
        $derived = CommissionAccrualSql::withJoins(CommissionAccrual::query())
            ->select('commission_accruals.id')
            ->selectRaw(CommissionAccrualSql::invoiceLabel().' as invoice_label');

        $matching = CommissionAccrual::query()->fromSub($derived, 'derived')->select('derived.id');
        app(FilterApplier::class)->apply($matching, 'derived.invoice_label', $columnConfig, $filter);

        $query->whereIn('commission_accruals.id', $matching);
    }
}
