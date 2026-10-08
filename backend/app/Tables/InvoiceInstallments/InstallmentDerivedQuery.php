<?php

namespace App\Tables\InvoiceInstallments;

use App\Enums\InstallmentStatus;
use App\Models\InvoiceInstallment;
use App\Services\Table\FilterApplier;
use App\Tables\Invoices\InvoiceDerivedQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Filters, sort and distinct values of the `invoice-installments` columns
 * (spec 0197). The joins repeat plain column names, so every column is
 * qualified through InstallmentSql; values are validated against an allow-list
 * or bound as parameters, never concatenated.
 */
final class InstallmentDerivedQuery
{
    private const int MAX_VALUES = 200;

    /** Columns whose set filter / distinct values are an SQL expression. */
    private const array SET_COLUMNS = ['customer', 'work_order', 'company', 'company_site', 'operational_site', 'payment_method_code', 'due_month'];

    /** Columns with a dedicated filter instead of the generic one on a plain column. */
    private const array SPECIAL_COLUMNS = ['invoice_number_label', 'days_overdue', 'status', 'overdue'];

    /**
     * Handles every filterable column of the domain; false for an unknown one.
     *
     * @param  Builder<InvoiceInstallment>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public static function applyFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        match (true) {
            $columnId === 'invoice_number_label' => InvoiceDerivedQuery::applyFilter($query, 'number_label', $filter),
            $columnId === 'days_overdue' => self::applyDaysOverdue($query, $columnConfig, $filter),
            $columnId === 'status' => self::applyStatus($query, $filter),
            $columnId === 'overdue' => self::applyOverdue($query, $filter),
            in_array($columnId, self::SET_COLUMNS, true) => self::applySet($query, (string) InstallmentSql::label($columnId), $filter),
            isset(InstallmentSql::PLAIN_COLUMNS[$columnId]) => app(FilterApplier::class)->apply($query, InstallmentSql::PLAIN_COLUMNS[$columnId], $columnConfig, $filter),
            default => null,
        };

        return in_array($columnId, [...self::SPECIAL_COLUMNS, ...self::SET_COLUMNS], true)
            || isset(InstallmentSql::PLAIN_COLUMNS[$columnId]);
    }

    /**
     * @param  Builder<InvoiceInstallment>  $query
     */
    public static function applySort(Builder $query, string $columnId, string $direction): bool
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        if ($columnId === 'invoice_number_label') {
            $query->orderBy('invoices.year', $direction)->orderBy('invoices.number', $direction);

            return true;
        }

        if ($columnId === 'days_overdue') {
            [$expression, $bindings] = InstallmentSql::daysOverdue();
            $query->orderByRaw("{$expression} {$direction}", array_fill(0, $bindings, self::today()));

            return true;
        }

        $expression = match ($columnId) {
            'status' => InstallmentSql::statusRank(),
            'work_order' => InstallmentSql::workOrderLabel(),
            'operational_site' => InstallmentSql::operationalSiteLabel(),
            default => null,
        };

        if ($expression !== null) {
            $query->orderByRaw("{$expression} {$direction}");

            return true;
        }

        if (! isset(InstallmentSql::PLAIN_COLUMNS[$columnId])) {
            return false;
        }

        $query->orderBy(InstallmentSql::PLAIN_COLUMNS[$columnId], $direction);

        return true;
    }

    /**
     * Distinct values of the set columns; null for any other column.
     *
     * @param  Builder<InvoiceInstallment>  $query
     * @return array<int, string|null>|null
     */
    public static function distinctValues(string $columnId, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId === 'status') {
            return InstallmentStatus::values();
        }

        if ($columnId === 'overdue') {
            return InstallmentColumnCatalog::OVERDUE_VALUES;
        }

        if (! in_array($columnId, self::SET_COLUMNS, true)) {
            return null;
        }

        $expression = (string) InstallmentSql::label($columnId);
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
     * @param  Builder<InvoiceInstallment>  $query
     */
    private static function plain(Builder $query): QueryBuilder
    {
        return (clone $query)->reorder()->toBase()->select([]);
    }

    /**
     * @param  Builder<InvoiceInstallment>  $query
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
     * @param  Builder<InvoiceInstallment>  $query
     * @param  array<string, mixed>  $filter
     */
    private static function applyStatus(Builder $query, array $filter): void
    {
        $conditions = [
            InstallmentStatus::Unpaid->value => InstallmentSql::unpaid(),
            InstallmentStatus::PartiallyPaid->value => InstallmentSql::partiallyPaid(),
            InstallmentStatus::Paid->value => InstallmentSql::paid(),
        ];
        $selected = array_values(array_intersect(array_keys($conditions), self::stringValues($filter)));

        if ($selected === []) {
            return;
        }

        $query->where(static function (Builder $group) use ($conditions, $selected): void {
            foreach ($selected as $status) {
                $group->orWhereRaw('('.$conditions[$status].')');
            }
        });
    }

    /**
     * @param  Builder<InvoiceInstallment>  $query
     * @param  array<string, mixed>  $filter
     */
    private static function applyOverdue(Builder $query, array $filter): void
    {
        $selected = array_values(array_intersect(InstallmentColumnCatalog::OVERDUE_VALUES, self::stringValues($filter)));

        // Both values (or none) select everything.
        if (count($selected) !== 1) {
            return;
        }

        $query->whereRaw($selected[0] === 'yes' ? InstallmentSql::overdue() : 'NOT '.InstallmentSql::overdue(), [self::today()]);
    }

    /**
     * The days-overdue value only exists as an expression, so the number
     * filter runs on a derived table and the installments are matched by id.
     *
     * @param  Builder<InvoiceInstallment>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    private static function applyDaysOverdue(Builder $query, array $columnConfig, array $filter): void
    {
        [$expression, $bindings] = InstallmentSql::daysOverdue();

        $derived = DB::table('invoice_installments')
            ->select('invoice_installments.id')
            ->selectRaw("{$expression} as days_overdue", array_fill(0, $bindings, self::today()));

        $matching = InvoiceInstallment::query()->fromSub($derived, 'derived')->select('derived.id');
        app(FilterApplier::class)->apply($matching, 'derived.days_overdue', $columnConfig, $filter);

        $query->whereIn('invoice_installments.id', $matching);
    }

    private static function today(): string
    {
        return Carbon::today()->toDateString();
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private static function stringValues(array $filter): array
    {
        return is_array($filter['values'] ?? null)
            ? array_values(array_filter($filter['values'], 'is_string'))
            : [];
    }
}
