<?php

declare(strict_types=1);

namespace App\Support\QuoteWorkflows;

use App\Models\Quote;
use App\Models\QuoteLine;
use App\Services\ProductCategories\CategoryHierarchy;

/**
 * The product-category BRANCH a Quote belongs to (spec 0092): for every
 * offer line's own category, that category plus every ancestor above it,
 * each with the SHORTEST distance from a line's own category (0 = the line's
 * own category, 1 = its parent, ...).
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
 * Assumes the caller already eager-loaded `offerLines.product` — the same
 * contract QuoteCriterionFieldRegistry states for every other native field.
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

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * $quote's whole branch as `category id => shortest distance`, empty when
     * the quote has no offer line carrying a product category.
     *
     * @return array<int, int>
     */
    public function distancesFor(Quote $quote): array
    {
        return $this->distances[spl_object_id($quote)] ??= $this->build($quote);
    }

    /**
     * @return array<int, int>
     */
    private function build(Quote $quote): array
    {
        // Step 1: the categories the quote sits on directly (revenue lines
        // only, never cost lines — same source as offerLineValues()).
        $lineCategoryIds = $quote->offerLines
            ->map(static fn (QuoteLine $line): ?int => $line->product?->category_id)
            ->filter()
            ->unique()
            ->map(static fn (mixed $categoryId): int => (int) $categoryId)
            ->all();

        if ($lineCategoryIds === []) {
            return [];
        }

        // Step 2: climb from each of them, keeping the shortest distance when
        // two lines reach the same ancestor from different depths.
        $parents = $this->hierarchy->parentIdMap();
        $distances = [];

        foreach ($lineCategoryIds as $categoryId) {
            $this->climb($categoryId, $parents, $distances);
        }

        return $distances;
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
