<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Models\ProductCategory;
use App\Models\Quote;
use App\Services\ProductCategories\CategoryManagerLabelResolver;
use Illuminate\Support\Collection;

/**
 * Resolves the "Gestore Account" labels an Offerta's team is shown (spec
 * 0087, D-8): a gemello of `App\Services\Opportunities\
 * OpportunityManagerLabelResolver` (same unicity rule: [] unless every
 * DISTINCT category resolves to the SAME effective manager labels), reading
 * from TWO sources in order:
 *   1. the categories of the products on the Offerta's own REVENUE lines
 *      (`offerLines.product.category` — `quote_lines` carries no
 *      `product_category_id` of its own, spec 0065 D-7);
 *   2. FALLBACK, only when the Offerta has NO revenue line yet: the
 *      categories of its Opportunita's own `productLines`.
 * The fallback is not cosmetic: at create time the GA are already
 * prefilled from the Opportunita' (D-5) while the offer lines do not exist
 * yet — without it the team section would show the DEFAULT labels the
 * instant it renders, then jump to the product-driven ones the moment the
 * first line is added. The unicity rule applies WITHIN each source, never
 * ACROSS them: a quote with lines that disagree stays [] even though its
 * opportunity might resolve to something specific.
 *
 * Relies on the caller having eager-loaded `offerLines.product.category`
 * AND `opportunity.productLines.productCategory` (QuoteService::
 * DETAIL_RELATIONS does both) so this never lazy-loads under
 * Model::preventLazyLoading().
 */
final class QuoteManagerLabelResolver
{
    public function __construct(private readonly CategoryManagerLabelResolver $categoryLabels) {}

    /**
     * @return array<int, string>
     */
    public function resolve(Quote $quote): array
    {
        $categories = $quote->offerLines->isEmpty()
            ? $this->opportunityCategories($quote)
            : $this->offerLineCategories($quote);

        return $this->resolveFromCategories($categories);
    }

    /**
     * @return Collection<int, ProductCategory>
     */
    private function offerLineCategories(Quote $quote): Collection
    {
        return $quote->offerLines
            ->pluck('product.category')
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, ProductCategory>
     */
    private function opportunityCategories(Quote $quote): Collection
    {
        return $quote->opportunity->productLines
            ->pluck('productCategory')
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * @param  Collection<int, ProductCategory>  $categories
     * @return array<int, string>
     */
    private function resolveFromCategories(Collection $categories): array
    {
        if ($categories->isEmpty()) {
            return [];
        }

        $resolved = $categories->map(
            fn (ProductCategory $category): array => $this->categoryLabels->effectiveManagerLabels($category),
        );

        $first = $resolved->first();

        return $resolved->every(fn (array $labels): bool => $labels === $first) ? $first : [];
    }
}
