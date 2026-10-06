<?php

namespace App\Tables\Invoices;

use App\Models\Invoice;
use App\Models\Registry;
use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Filters, sort and distinct values for the invoices columns that are not
 * plain `invoices` columns (spec 0194). Every value is validated against an
 * allow-list or bound as a parameter: no raw input reaches the SQL.
 */
final class InvoiceDerivedQuery
{
    public const int MIN_YEAR = 2000;

    public const int MAX_YEAR = 2100;

    private const int MAX_VALUES = 50;

    /** Set filters resolved by name through a relation: column id => [relation, name column]. */
    private const array NAMED_RELATIONS = [
        'company' => ['company', 'denomination'],
        'payment_method' => ['paymentMethod', 'name'],
    ];

    /**
     * @param  Builder<Invoice>  $query
     * @param  array<string, mixed>  $filter
     */
    public static function applyFilter(Builder $query, string $columnId, array $filter): bool
    {
        match (true) {
            $columnId === 'document_year' => self::applyDatePart($query, $filter, 'whereYear', self::MIN_YEAR, self::MAX_YEAR),
            $columnId === 'document_month' => self::applyDatePart($query, $filter, 'whereMonth', 1, 12),
            $columnId === 'customer' => self::applyCustomer($query, $filter),
            $columnId === 'number_label' => self::applyNumberLabel($query, $filter),
            isset(self::NAMED_RELATIONS[$columnId]) => self::applyNamedSet($query, $columnId, $filter),
            default => null,
        };

        return in_array($columnId, ['document_year', 'document_month', 'customer', 'number_label', ...array_keys(self::NAMED_RELATIONS)], true);
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public static function applySort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId !== 'customer') {
            return false;
        }

        $query->orderBy(
            Registry::query()->select('name')->whereColumn('registries.id', 'invoices.customer_registry_id'),
            $direction,
        );

        return true;
    }

    /**
     * Distinct values of the derived set columns; null for any other column.
     *
     * @param  Builder<Invoice>  $query
     * @return array<int, string>|null
     */
    public static function distinctValues(string $columnId, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId === 'document_month') {
            return InvoiceColumnCatalog::MONTHS;
        }

        if ($columnId === 'document_year') {
            return (clone $query)->reorder()->select('invoices.document_date')->distinct()->pluck('document_date')
                ->map(static fn (mixed $date): string => substr((string) $date, 0, 4))
                ->unique()->sortDesc()->values()->take($limit)->all();
        }

        if (! isset(self::NAMED_RELATIONS[$columnId])) {
            return null;
        }

        $table = $columnId === 'company' ? 'companies' : 'payment_methods';
        $foreignKey = $columnId === 'company' ? 'company_id' : 'payment_method_id';
        $nameColumn = self::NAMED_RELATIONS[$columnId][1];

        return DB::table($table)
            ->whereIn('id', (clone $query)->reorder()->select('invoices.'.$foreignKey))
            ->when($search !== null && $search !== '', function ($builder) use ($search, $nameColumn): void {
                $builder->where($nameColumn, 'like', '%'.app(FilterApplier::class)->escapeLike($search).'%');
            })
            ->orderBy($nameColumn)
            ->limit($limit)
            ->pluck($nameColumn)
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * @param  Builder<Invoice>  $query
     * @param  array<string, mixed>  $filter
     */
    private static function applyDatePart(Builder $query, array $filter, string $method, int $min, int $max): void
    {
        $values = [];

        foreach (self::stringValues($filter) as $value) {
            $int = filter_var($value, FILTER_VALIDATE_INT);

            if ($int !== false && $int >= $min && $int <= $max) {
                $values[] = $int;
            }
        }

        if ($values === []) {
            return;
        }

        $query->where(static function (Builder $group) use ($values, $method): void {
            foreach (array_unique($values) as $value) {
                $group->{'or'.ucfirst($method)}('invoices.document_date', $value);
            }
        });
    }

    /**
     * @param  Builder<Invoice>  $query
     * @param  array<string, mixed>  $filter
     */
    private static function applyCustomer(Builder $query, array $filter): void
    {
        $term = self::textTerm($filter);

        if ($term === null) {
            return;
        }

        $query->whereHas('customer', static function (Builder $related) use ($term): void {
            $related->where('name', 'like', '%'.app(FilterApplier::class)->escapeLike($term).'%');
        });
    }

    /**
     * "12/2026" matches number and year, "12" the number only; anything else
     * matches nothing.
     *
     * @param  Builder<Invoice>  $query
     * @param  array<string, mixed>  $filter
     */
    private static function applyNumberLabel(Builder $query, array $filter): void
    {
        $term = self::textTerm($filter);

        if ($term === null) {
            return;
        }

        if (preg_match('/^(\d{1,9})(?:\/(\d{4}))?$/', $term, $matches) !== 1) {
            $query->where('invoices.id', '<', 0);

            return;
        }

        $query->where('invoices.number', (int) $matches[1]);

        if (isset($matches[2])) {
            $query->where('invoices.year', (int) $matches[2]);
        }
    }

    /**
     * @param  Builder<Invoice>  $query
     * @param  array<string, mixed>  $filter
     */
    private static function applyNamedSet(Builder $query, string $columnId, array $filter): void
    {
        $names = self::stringValues($filter);

        if ($names === []) {
            return;
        }

        [$relation, $nameColumn] = self::NAMED_RELATIONS[$columnId];

        $query->whereHas($relation, static function (Builder $related) use ($names, $nameColumn): void {
            $related->whereIn($nameColumn, $names);
        });
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private static function stringValues(array $filter): array
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

    /**
     * @param  array<string, mixed>  $filter
     */
    private static function textTerm(array $filter): ?string
    {
        $term = $filter['filter'] ?? null;

        return is_string($term) && trim($term) !== '' ? trim($term) : null;
    }
}
