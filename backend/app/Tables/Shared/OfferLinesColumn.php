<?php

declare(strict_types=1);

namespace App\Tables\Shared;

use App\Enums\QuoteLineType;
use App\Models\Product;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The "Linee di prodotto" grid column (spec 0086, D-7): replaces
 * `products_of_interest` on the `request-management` domain ONLY — the
 * `opportunities` domain keeps its own `App\Tables\Shared\ProductsOfInterestColumn`
 * untouched (out of scope, AC-009).
 *
 * Unlike `products_of_interest` (a pivot, belongsToMany), this projects the
 * products of the Quote's own REVENUE lines (`Quote::offerLines()`, spec
 * 0065 D-11): a COST line's product never appears here (AC-007). Read-only
 * (AC-021/AC-022): no editor, no `relation` block — the offer's lines are
 * written exclusively by the Offerte module, never from this grid.
 *
 * Not sortable (no single related row to order by), `set`-filterable by the
 * related product's own `name`, same bound/never-raw discipline as
 * ProductsOfInterestColumn (backend.md §8).
 */
final class OfferLinesColumn
{
    public const string COLUMN_ID = 'offer_lines';

    /** `quote_lines`, the join table between a Quote and its Products. */
    private const string LINES_TABLE = 'quote_lines';

    private const string PRODUCT_FK = 'product_id';

    /** Maximum number of names honoured in the set filter (caps the WHERE IN cardinality, defence in depth). */
    private const int MAX_FILTER_VALUES = 200;

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
            'editable' => false,
        ];
    }

    /**
     * Set filter: a `whereHas` on the Quote's own REVENUE lines' product name
     * — `offerLines` already constrains `line_type` (never a COST line's
     * product), bound and never raw.
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

        $query->whereHas('offerLines.product', static function (Builder $relatedQuery) use ($values): void {
            $relatedQuery->whereIn('name', $values);
        });
    }

    /**
     * Excel-like distinct values (spec 0004/0005): the REVENUE-line product
     * names of the quotes matching $query, via a join through `quote_lines`
     * explicitly filtered on `line_type` (a raw DB::table join has no
     * `offerLines` scope to inherit it from).
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    public static function distinctValues(Builder $query, ?string $search, int $limit): array
    {
        $quoteIds = (clone $query)->select('quotes.id');

        return DB::table(self::LINES_TABLE)
            ->join('products', 'products.id', '=', self::LINES_TABLE.'.'.self::PRODUCT_FK)
            ->where(self::LINES_TABLE.'.line_type', QuoteLineType::Revenue->value)
            ->whereIn(self::LINES_TABLE.'.quote_id', $quoteIds)
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
     * The row projection: the distinct products of the Quote's own REVENUE
     * lines as `{id, name}` refs — read from the eager-loaded `offerLines.product`
     * relation, never queried here. Deduplicated by product id: several lines
     * of the same product (different quantities) project one chip, not one
     * per line.
     *
     * @return array<string, mixed>
     */
    public static function project(Quote $quote): array
    {
        return [
            self::COLUMN_ID => $quote->offerLines
                ->pluck('product')
                ->filter()
                ->unique(static fn (Product $product): int => $product->id)
                ->map(static fn (Product $product): array => ['id' => $product->id, 'name' => $product->name])
                ->values()
                ->all(),
        ];
    }
}
