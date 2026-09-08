<?php

declare(strict_types=1);

namespace App\Support\QuoteWorkflows;

use App\Models\OpportunityProductLine;
use App\Models\Quote;
use App\Models\QuoteLine;
use Illuminate\Support\Collection;

/**
 * The "funzione aziendale" + "categoria prodotto" classification a Quote is
 * matched on by the workflow criteria (spec 0083, D-7), read from TWO sources
 * in order (user directive 2026-09-08):
 *   1. the categories of the products on the offer's OWN REVENUE lines
 *      (`offerLines.product.category`, never cost lines);
 *   2. FALLBACK, only when the offer has NO revenue line: the
 *      `business_function_id`/`product_category_id` pairs of its
 *      Opportunita's own `productLines`.
 *
 * The fallback is the fix for the defect the directive reports: a request
 * born without offer lines resolved onto NO category at all, so every
 * category-criteria workflow missed it and the offer fell back to the GLOBAL
 * default status set — while the very same grid was already listing that
 * request under its Opportunita's category tab (RequestCategoryTabsResolver
 * reads `opportunity_product_lines`). The classification was known; only the
 * workflow resolution refused to look at it.
 *
 * Same two-source shape, and same "only when there is no revenue line"
 * trigger, as App\Services\Quotes\QuoteManagerLabelResolver (spec 0087, D-8):
 * the two answer different questions off the same fallback rule, so the rule
 * reads identically in both places. `quote_lines.product_id` is NOT NULL, so
 * "the offer has no revenue line" IS "the offer has no product".
 *
 * Two consumers share it, which is why it is a collaborator rather than more
 * code inside either: QuoteCriterionFieldRegistry (the
 * `product_category_id`/`business_function_id` criteria) and
 * CategoryBranchResolver (the `product_category_branch_id` criterion, which
 * widens the same categories to their ancestors) — a fallback applied by only
 * one of them would let a branch criterion and an exact-category criterion
 * disagree about the very same offer.
 *
 * Assumes the caller already eager-loaded `offerLines.product.category` and
 * `opportunity.productLines` — the contract QuoteWorkflowResolver::resolve()
 * enforces with its own `loadMissing()`, so nothing here lazy-loads under
 * Model::preventLazyLoading().
 */
final class QuoteClassificationSource
{
    /**
     * The distinct product categories $quote is classified by.
     *
     * @return array<int, int>
     */
    public function categoryIds(Quote $quote): array
    {
        if ($quote->offerLines->isEmpty()) {
            return $this->distinctIds(
                $this->opportunityProductLines($quote)
                    ->map(static fn (OpportunityProductLine $line): ?int => $line->product_category_id),
            );
        }

        return $this->distinctIds(
            $quote->offerLines->map(static fn (QuoteLine $line): ?int => $line->product?->category_id),
        );
    }

    /**
     * The distinct business functions $quote is classified by — a revenue
     * line reaches its own through the product's category, while an
     * Opportunita' product line carries the pair's own column.
     *
     * @return array<int, int>
     */
    public function businessFunctionIds(Quote $quote): array
    {
        if ($quote->offerLines->isEmpty()) {
            return $this->distinctIds(
                $this->opportunityProductLines($quote)
                    ->map(static fn (OpportunityProductLine $line): ?int => $line->business_function_id),
            );
        }

        return $this->distinctIds(
            $quote->offerLines->map(static fn (QuoteLine $line): ?int => $line->product?->category?->business_function_id),
        );
    }

    /**
     * @return Collection<int, OpportunityProductLine>
     */
    private function opportunityProductLines(Quote $quote): Collection
    {
        // A transient Quote (never persisted, e.g. ValidatesQuoteWorkflowStatus'
        // resolution probe) has no parent to fall back to.
        return $quote->opportunity?->productLines ?? new Collection;
    }

    /**
     * @param  Collection<int, int|null>  $ids
     * @return array<int, int>
     */
    private function distinctIds(Collection $ids): array
    {
        return $ids
            ->filter()
            ->unique()
            ->values()
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}
