<?php

declare(strict_types=1);

namespace App\Tables\Shared;

use App\Models\Opportunity;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The "Prodotti di interesse" grid column (user directive 2026-07-23), shared
 * VERBATIM by the `opportunities` and `request-management` domains — the two
 * grids over the SAME rows, exactly like the form picker
 * (ProductsOfInterestField) is shared by the opportunity form and the work
 * panel. One declaration, so the two can never drift.
 *
 * It is a PIVOT (belongsToMany) column: not sortable (no single related row to
 * order by), set-filterable by the product name, and inline-editable through
 * the generic `multiselect` editor — whose value is the whole id collection
 * (a full-replace sync), validated by CellValueValidator and written by the
 * ONE writer both channels already use (OpportunityProductInterestWriter).
 *
 * `relation.scope` mirrors the form picker's scope: the editor sends the row's
 * OWN product-line categories as `category_ids`, so the dropdown offers the
 * same subset the form does — and, like the form, offers no "show the whole
 * catalogue" escape (`relation.lockScope`): both domains REFUSE a product
 * outside those categories (ProductCategoryCoherence), so the escape would
 * only lead to a 422.
 */
final class ProductsOfInterestColumn
{
    /** The row key carrying the scope value the editor reads (see `relation.scope` below). */
    public const string SCOPE_COLUMN = 'product_category_ids';

    public const string COLUMN_ID = 'products_of_interest';

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
            'editable' => true,
            'editor' => 'multiselect',
            'relation' => [
                'resource' => 'products',
                'scope' => ['category_ids' => self::SCOPE_COLUMN],
                'lockScope' => true,
            ],
        ];
    }

    /** The belongsToMany pivot and its FK to `products`, used by the distinct-values join. */
    private const string PIVOT_TABLE = 'opportunity_product';

    private const string PIVOT_PRODUCT_FK = 'product_id';

    /** Maximum number of names honoured in the set filter (caps the WHERE IN cardinality, defence in depth). */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * Set filter: a `whereHas` on the related product's own `name` — bound,
     * never raw (backend.md §8), same shape as every other to-many column of
     * these two domains.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $values
     */
    public static function applyFilter(Builder $query, array $values): void
    {
        $values = array_slice($values, 0, self::MAX_FILTER_VALUES);

        if ($values === []) {
            return;
        }

        $query->whereHas('productsOfInterest', static function (Builder $relatedQuery) use ($values): void {
            $relatedQuery->whereIn('name', $values);
        });
    }

    /**
     * Excel-like distinct values (spec 0004/0005): the product names attached
     * to the opportunities matching $query, via a join through the pivot.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    public static function distinctValues(Builder $query, ?string $search, int $limit): array
    {
        $opportunityIds = (clone $query)->select('opportunities.id');

        return DB::table(self::PIVOT_TABLE)
            ->join('products', 'products.id', '=', self::PIVOT_TABLE.'.'.self::PIVOT_PRODUCT_FK)
            ->whereIn(self::PIVOT_TABLE.'.opportunity_id', $opportunityIds)
            ->when($search !== null && $search !== '', static function ($builder) use ($search): void {
                $builder->where('products.name', 'like', '%'.self::escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('products.name')
            ->limit($limit)
            ->pluck('products.name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /** Escape LIKE wildcards in user input so they are treated literally. */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * The row projection: the selected products as `{id, name}` refs (what the
     * cell renders AND what the editor pre-selects) plus the product-line
     * category ids the editor scopes its option list to. Both read from
     * relations the caller's baseQuery already eager-loads.
     *
     * @return array<string, mixed>
     */
    public static function project(Opportunity $opportunity): array
    {
        return [
            self::COLUMN_ID => $opportunity->productsOfInterest
                ->map(static fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    // Spec 0075, D-6: which category each product hangs from,
                    // so the product-lines cell editor can warn BEFORE the
                    // commit that dropping a category would leave it
                    // uncovered. The cell renderer reads `name` only.
                    'category_id' => $product->category_id === null ? null : (int) $product->category_id,
                ])
                ->all(),
            self::SCOPE_COLUMN => $opportunity->productLines
                ->pluck('product_category_id')
                ->unique()
                ->values()
                ->map(static fn (mixed $id): int => (int) $id)
                ->all(),
        ];
    }
}
