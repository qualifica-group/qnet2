<?php

declare(strict_types=1);

namespace App\Tables\Shared;

use App\Enums\QuoteLineType;
use App\Models\Product;
use App\Models\Quote;
use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\ProductCategories\CategoryRootResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The "Categoria madre" grid column of the Contratti and Offerte grids (user
 * directive 2026-10-15): the ROOT category (macrocategoria) of the categories
 * of the products on the Quote's REVENUE lines, right after `offer_lines`.
 *
 * Read-only and not sortable. The row value is the distinct list of
 * `{id, name}` roots, ordered by name. The root of a category is resolved in
 * PHP through CategoryRootResolver, which memoizes its two projection queries
 * on its instance: the column object is resolved once per table definition,
 * so a whole page costs at most two queries, never one per row.
 */
final class RootCategoriesColumn
{
    public const string COLUMN_ID = 'root_categories';

    /** Maximum number of names honoured in the set filter (caps the WHERE IN cardinality, defence in depth). */
    private const int MAX_FILTER_VALUES = 200;

    public function __construct(
        private readonly CategoryRootResolver $rootResolver,
        private readonly CategoryHierarchy $hierarchy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function declaration(string $label): array
    {
        return [
            'id' => self::COLUMN_ID,
            'label' => $label,
            'type' => 'text',
            'visible' => true,
            'sortable' => false,
            'filterable' => true,
            'filterType' => 'set',
        ];
    }

    /**
     * Read from the eager-loaded `offerLines.product` relation; never queried per row.
     *
     * @return array<string, mixed>
     */
    public function project(Quote $quote): array
    {
        $categoryIds = $quote->offerLines
            ->pluck('product')
            ->filter()
            ->map(static fn (Product $product): int => (int) $product->category_id)
            ->unique()
            ->values()
            ->all();

        return [self::COLUMN_ID => $this->rootsOf($categoryIds)];
    }

    /**
     * Set filter by root name: matches the quotes with a REVENUE-line product
     * in the root category itself or in any of its descendants. The blank
     * entry selects the quotes with no revenue-line product at all.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $values
     * @param  string  $quoteRelation  relation path to the quote (`quote.` from a `contracts` query)
     */
    public function applyFilter(Builder $query, array $values, bool $matchesBlank = false, string $quoteRelation = ''): void
    {
        $values = array_slice($values, 0, self::MAX_FILTER_VALUES);

        if ($values === [] && ! $matchesBlank) {
            return;
        }

        $categoryIds = $this->categoryIdsUnderRoots($values);

        $query->where(static function (Builder $group) use ($categoryIds, $matchesBlank, $quoteRelation): void {
            if ($categoryIds !== []) {
                $group->whereHas($quoteRelation.'offerLines.product', static function (Builder $relatedQuery) use ($categoryIds): void {
                    $relatedQuery->whereIn('category_id', $categoryIds);
                });
            }

            if ($matchesBlank) {
                $group->orWhereDoesntHave($quoteRelation.'offerLines.product');
            }
        });
    }

    /**
     * Excel-like distinct values: the root names of the REVENUE-line product
     * categories of the quotes matching $query, plus null when some of them
     * have no such line (the "(Vuoti)" entry).
     *
     * @param  Builder<Model>  $query
     * @param  string  $quoteIdColumn  the query's column holding the quote id
     * @param  string  $quoteRelation  relation path to the quote (`quote.` from a `contracts` query)
     * @return array<int, string|null>
     */
    public function distinctValues(
        Builder $query,
        ?string $search,
        int $limit,
        string $quoteIdColumn = 'quotes.id',
        string $quoteRelation = '',
    ): array {
        $categoryIds = DB::table('quote_lines')
            ->join('products', 'products.id', '=', 'quote_lines.product_id')
            ->where('quote_lines.line_type', QuoteLineType::Revenue->value)
            ->whereIn('quote_lines.quote_id', (clone $query)->select($quoteIdColumn))
            ->distinct()
            ->pluck('products.category_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $names = collect($this->rootsOf($categoryIds))->pluck('name');

        if ($search !== null && $search !== '') {
            $names = $names->filter(static fn (string $name): bool => mb_stripos($name, $search) !== false);
        }

        $values = $names->take($limit)->values()->all();

        if (($search === null || $search === '') && (clone $query)->whereDoesntHave($quoteRelation.'offerLines.product')->exists()) {
            array_unshift($values, null);
        }

        return $values;
    }

    /**
     * @param  array<int, int>  $categoryIds
     * @return array<int, array{id: int, name: string}>
     */
    private function rootsOf(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        return collect($this->rootResolver->rootSummariesFor($categoryIds))
            ->filter()
            ->unique('id')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Every category id (roots included) whose root is named in $names.
     *
     * @param  array<int, string>  $names
     * @return array<int, int>
     */
    private function categoryIdsUnderRoots(array $names): array
    {
        if ($names === []) {
            return [];
        }

        $allIds = array_keys($this->hierarchy->parentIdMap());

        return collect($this->rootResolver->rootSummariesFor($allIds))
            ->filter(static fn (?array $root): bool => $root !== null && in_array($root['name'], $names, true))
            ->keys()
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}
