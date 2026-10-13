<?php

namespace App\Tables\PurchaseInvoices;

use App\Models\Company;
use App\Models\PurchaseInvoice;
use App\Models\Registry;
use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filters, sort and distinct values of the `purchase-invoices` columns that
 * are not plain `purchase_invoices` columns (supplier and company, spec 0250).
 * Values are matched by name through a parameterised subquery: no raw input
 * reaches the SQL.
 */
final class PurchaseInvoiceDerivedQuery
{
    private const int MAX_VALUES = 50;

    /** Column id => [model, table, name column, foreign key on purchase_invoices]. */
    private const array NAMED = [
        'supplier' => [Registry::class, 'registries', 'name', 'supplier_id'],
        'company' => [Company::class, 'companies', 'denomination', 'company_id'],
    ];

    /**
     * @param  Builder<PurchaseInvoice>  $query
     * @param  array<string, mixed>  $filter
     */
    public static function applyFilter(Builder $query, string $columnId, array $filter): bool
    {
        if (! isset(self::NAMED[$columnId])) {
            return false;
        }

        [$model, , $nameColumn, $foreignKey] = self::NAMED[$columnId];
        $names = self::names($filter);

        if ($names !== []) {
            $query->whereIn('purchase_invoices.'.$foreignKey, $model::query()->whereIn($nameColumn, $names)->select('id'));
        }

        return true;
    }

    /**
     * @param  Builder<PurchaseInvoice>  $query
     */
    public static function applySort(Builder $query, string $columnId, string $direction): bool
    {
        if (! isset(self::NAMED[$columnId])) {
            return false;
        }

        [$model, $table, $nameColumn, $foreignKey] = self::NAMED[$columnId];

        $query->orderBy(
            $model::query()->select($nameColumn)->whereColumn("{$table}.id", 'purchase_invoices.'.$foreignKey),
            $direction === 'desc' ? 'desc' : 'asc',
        );

        return true;
    }

    /**
     * Distinct values of the supplier and company columns; null for any other column.
     *
     * @param  Builder<PurchaseInvoice>  $query
     * @return array<int, string>|null
     */
    public static function distinctValues(string $columnId, ?string $search, Builder $query, int $limit): ?array
    {
        if (! isset(self::NAMED[$columnId])) {
            return null;
        }

        [$model, , $nameColumn, $foreignKey] = self::NAMED[$columnId];

        return $model::query()
            ->whereIn('id', (clone $query)->reorder()->select('purchase_invoices.'.$foreignKey))
            ->when($search !== null && $search !== '', function ($builder) use ($search, $nameColumn): void {
                $builder->where($nameColumn, 'like', '%'.app(FilterApplier::class)->escapeLike((string) $search).'%');
            })
            ->orderBy($nameColumn)
            ->limit($limit)
            ->pluck($nameColumn)
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private static function names(array $filter): array
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return [];
        }

        return array_slice(array_values(array_filter(
            array_map(static fn (mixed $value): ?string => is_string($value) || is_int($value) ? trim((string) $value) : null, $values),
            static fn (?string $value): bool => $value !== null && $value !== '',
        )), 0, self::MAX_VALUES);
    }
}
