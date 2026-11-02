<?php

declare(strict_types=1);

namespace App\Tables\CategoryTabs;

use App\Enums\QuoteLineType;
use Closure;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The quotes that carry a REVENUE line on a product of one category (spec
 * 0335, D-1, no fallback): the single rule behind the category scope of the
 * Offerte grid and, through `contracts.quote_id`, of the Contratti grid (D-8).
 */
final class RevenueCategoryQuoteIds
{
    /**
     * A `whereIn` subquery callback selecting `quote_lines.quote_id`, for one
     * category or for a whole branch (spec 0337: the root and its descendants).
     *
     * @param  int|array<int, int>  $categoryIds
     * @return Closure(QueryBuilder): QueryBuilder
     */
    public static function subquery(int|array $categoryIds): Closure
    {
        return static fn (QueryBuilder $lines): QueryBuilder => $lines
            ->select('quote_lines.quote_id')
            ->from('quote_lines')
            ->join('products', 'products.id', '=', 'quote_lines.product_id')
            ->where('quote_lines.line_type', QuoteLineType::Revenue->value)
            ->whereIn('products.category_id', (array) $categoryIds);
    }
}
