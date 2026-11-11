<?php

namespace App\Services\ProductCategories;

use App\Enums\CategoryUsageArea;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The record side of the usage area (spec 0389 rev.2, D-6): a record of a
 * module is hidden when EVERY category of its opportunity is reserved to the
 * other area. A record with no classification, or with at least one category
 * used in the module (its own area or `all`), stays visible: a mixed card
 * belongs to both modules.
 *
 * Static and stateless like the visibility scopes it sits beside, so a
 * TableDefinition::baseQuery() can call it inline.
 */
final class UsageAreaRowFilter
{
    /**
     * Narrows $query, rooted on (or joined to) the table holding
     * $opportunityColumn, to the records visible in $module. No-op while no
     * category is reserved to the other area: the default catalogue adds no
     * subquery to the grids.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  string  $opportunityColumn  qualified column holding the opportunity id (e.g. `quotes.opportunity_id`)
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, string $opportunityColumn, CategoryUsageArea $module): Builder
    {
        if (! self::anyHiddenIn($module)) {
            return $query;
        }

        $visibleAreas = CategoryUsageArea::valuesVisibleIn($module);

        return $query->where(fn (Builder $visible) => $visible
            ->whereNotExists(fn (QueryBuilder $lines) => self::linesOf($lines, $opportunityColumn))
            ->orWhereExists(fn (QueryBuilder $lines) => self::linesOf($lines, $opportunityColumn)
                ->join('product_categories', 'product_categories.id', '=', 'opportunity_product_lines.product_category_id')
                ->whereIn('product_categories.usage_area', $visibleAreas)));
    }

    /**
     * The same rule on a loaded opportunity (a record opened by its link).
     */
    public static function allows(?Opportunity $opportunity, CategoryUsageArea $module): bool
    {
        $lines = $opportunity?->loadMissing('productLines.productCategory')->productLines;

        if ($lines === null || $lines->isEmpty()) {
            return true;
        }

        return $lines->contains(
            static fn ($line): bool => $line->productCategory === null || $line->productCategory->usage_area->allows($module),
        );
    }

    private static function anyHiddenIn(CategoryUsageArea $module): bool
    {
        return ProductCategory::query()
            ->whereNotIn('usage_area', CategoryUsageArea::valuesVisibleIn($module))
            ->exists();
    }

    private static function linesOf(QueryBuilder $lines, string $opportunityColumn): QueryBuilder
    {
        return $lines->selectRaw('1')
            ->from('opportunity_product_lines')
            ->whereColumn('opportunity_product_lines.opportunity_id', $opportunityColumn);
    }
}
