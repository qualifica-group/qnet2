<?php

declare(strict_types=1);

namespace App\Support\QuoteWorkflows;

use App\Models\Quote;
use App\Services\ProductCategories\CategoryHierarchy;

/**
 * The product-category BRANCH a Quote belongs to (spec 0092): for every
 * category the offer is classified by, that category plus every ancestor
 * above it, each with the SHORTEST distance from the classifying category
 * (0 = that category itself, 1 = its parent, ...).
 *
 * WHICH categories those are is QuoteClassificationSource's decision, never
 * this class': the offer's own revenue lines, or — when it has none — its
 * Opportunita's product lines (user directive 2026-09-08). Reading the lines
 * directly here would let a branch criterion disagree with an
 * exact-category one about the very same offer.
 *
 * Two consumers share it, which is why it is a collaborator rather than more
 * code inside either: QuoteCriterionFieldRegistry reads the KEYS (which
 * `product_category_branch_id` values the quote matches, D-2 "the category
 * itself or any ancestor") and QuoteWorkflowResolver reads the VALUES (how
 * far up a workflow matched, the D-3 tie-break that lets the closest branch
 * win).
 *
 * Registered `scoped` (AppServiceProvider): both consumers must share ONE
 * instance so the tree projection is read once per request, and the memo must
 * not outlive the request (a reparented category would otherwise be resolved
 * against a stale tree forever on a queue worker).
 *
 * Assumes the caller already eager-loaded `offerLines.product` and
 * `opportunity.productLines` — the same contract QuoteCriterionFieldRegistry
 * states for every other native field.
 */
final class CategoryBranchResolver
{
    /**
     * Per-Quote memo, keyed by spl_object_id: resolve() walks the same quote
     * once per criterion AND once per candidate workflow, and a Quote being
     * created has no id yet to key on.
     *
     * @var array<int, array<int, int>>
     */
    private array $distances = [];

    public function __construct(
        private readonly CategoryHierarchy $hierarchy,
        private readonly QuoteClassificationSource $classification,
    ) {}

    /**
     * $quote's whole branch as `category id => shortest distance`, empty when
     * neither the offer's revenue lines nor its Opportunita's product lines
     * carry a product category.
     *
     * @return array<int, int>
     */
    public function distancesFor(Quote $quote): array
    {
        return $this->distances[spl_object_id($quote)] ??= $this->build($quote);
    }

    /**
     * The same branch projection for an explicit list of classifying
     * categories (spec 0322: a work order is classified by its own lines,
     * not by a Quote). Not memoized: callers memoize per subject.
     *
     * @param  array<int, int>  $categoryIds
     * @return array<int, int>
     */
    public function distancesForCategoryIds(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        // Climb from each of them, keeping the shortest distance when two of
        // them reach the same ancestor from different depths.
        $parents = $this->hierarchy->parentIdMap();
        $distances = [];

        foreach ($categoryIds as $categoryId) {
            $this->climb($categoryId, $parents, $distances);
        }

        return $distances;
    }

    /**
     * @return array<int, int>
     */
    private function build(Quote $quote): array
    {
        // The categories the quote sits on directly — the SAME set the
        // exact-category criterion matches on, fallback included.
        return $this->distancesForCategoryIds($this->classification->categoryIds($quote));
    }

    /**
     * @param  array<int, int|null>  $parents
     * @param  array<int, int>  $distances
     */
    private function climb(int $categoryId, array $parents, array &$distances): void
    {
        $currentId = $categoryId;
        $distance = 0;
        // The visited set doubles as the cycle guard: corrupted data cannot
        // loop forever, and no depth constant is needed.
        $visited = [];

        while ($currentId !== null && ! isset($visited[$currentId])) {
            $visited[$currentId] = true;

            if (! isset($distances[$currentId]) || $distances[$currentId] > $distance) {
                $distances[$currentId] = $distance;
            }

            $currentId = $parents[$currentId] ?? null;
            $distance++;
        }
    }
}
