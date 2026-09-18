<?php

namespace App\Tables\Products;

use App\Enums\ProductUsage;
use App\Tables\Concerns\HandlesBlankSetFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The `usages` column of the `products` grid (spec 0142): the JSON set of
 * App\Enums\ProductUsage values, rendered as `tags` localized through the
 * `product_usage` enum key. Extracted out of ProductsTableDefinition
 * (file-size split, engineering.md §6), mirroring ProductRelationColumns.
 *
 * - Filter: `set` over the enum VALUES, allow-listed through
 *   ProductUsage::tryFrom() and matched with whereJsonContains (never raw
 *   input in SQL — backend.md §8); several values match ANY of them.
 * - Sort: on the stored JSON text. Product keeps it in canonical case order,
 *   so one set is always one string and the sort groups identical sets
 *   (ascending: cost only, both, sellable only). The expression is a
 *   constant: only the allow-listed direction varies.
 */
final class ProductUsageColumn
{
    use HandlesBlankSetFilter;

    public const string COLUMN_ID = 'usages';

    /** The config/config.php `form_enums` key the client localizes the values with. */
    public const string ENUM_KEY = 'product_usage';

    private const string SORT_EXPRESSION = 'CAST(products.usages AS CHAR)';

    /**
     * @return array<string, mixed>
     */
    public static function declaration(): array
    {
        return [
            'id' => self::COLUMN_ID,
            'label' => 'products.columns.usages',
            'type' => 'tags',
            'visible' => true,
            'sortable' => true,
            'filterable' => true,
            'filterType' => 'set',
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, array $filter): void
    {
        $usages = $this->filterUsages($filter);
        $matchesBlank = $this->matchesBlankEntry($filter);

        if ($usages === [] && ! $matchesBlank) {
            return;
        }

        $query->where(static function (Builder $group) use ($usages, $matchesBlank): void {
            foreach ($usages as $usage) {
                $group->orWhereJsonContains('products.usages', $usage->value);
            }

            if ($matchesBlank) {
                $group->orWhereNull('products.usages');
            }
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $direction): void
    {
        $query->orderByRaw(self::SORT_EXPRESSION.' '.(strtolower($direction) === 'desc' ? 'desc' : 'asc'));
    }

    /**
     * The enum values carried by at least one product matching `$query`
     * (already scoped by every OTHER active filter), in case order. `$search`
     * matches the raw value or the English label.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string|null>
     */
    public function distinctValues(?string $search, Builder $query): array
    {
        $needle = $search === null ? '' : mb_strtolower(trim($search));
        $values = [];

        foreach (ProductUsage::cases() as $usage) {
            $matchesSearch = $needle === ''
                || str_contains(mb_strtolower($usage->value), $needle)
                || str_contains(mb_strtolower($usage->label()), $needle);

            if ($matchesSearch && (clone $query)->whereJsonContains('products.usages', $usage->value)->exists()) {
                $values[] = $usage->value;
            }
        }

        return $this->withBlankEntry($values, $search, fn (): bool => (clone $query)
            ->whereNull('products.usages')
            ->exists());
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, ProductUsage>
     */
    private function filterUsages(array $filter): array
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): ?ProductUsage => is_string($value) ? ProductUsage::tryFrom($value) : null,
            $values,
        )));
    }
}
