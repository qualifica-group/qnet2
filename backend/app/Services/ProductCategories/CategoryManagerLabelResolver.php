<?php

namespace App\Services\ProductCategories;

use App\Models\ProductCategory;

/**
 * Read-side resolution of the product-category tree's "Gestore Account"
 * labels (spec 0080): a category's EFFECTIVE manager labels are its own
 * `manager_labels` UNION its inherited ancestors', merged GRANULARLY per
 * POSITION (a category defining only GA2 still inherits GA1/GA3/GA4 from its
 * ancestors) — unlike CategoryHierarchy::effectiveAttributes(), which merges
 * whole attribute rows by id, not sub-fields of a single JSON map.
 *
 * A dedicated class rather than a CategoryHierarchy method: that file is
 * already at engineering.md's 500-line hard limit, the same reasoning
 * RequiresQuoteInheritance/CategoryManagementModeInheritance already give for
 * living beside it. Its ancestor walk is self-contained (not
 * CategoryHierarchy::inheritedAncestors(), which is scoped to
 * AttributeContext) so this feature can never regress the pre-existing
 * attribute-inheritance tests — spec 0080's explicit blast-radius
 * constraint.
 */
final class CategoryManagerLabelResolver
{
    /**
     * Defensive cap on the ancestor walk, mirroring CategoryHierarchy's: the
     * write-side anti-cycle guard (ProductCategoryService) prevents a real
     * cycle from ever being persisted, so this only guards against corrupted
     * data looping forever.
     */
    private const int MAX_DEPTH = 100;

    /**
     * $category's OWN manager labels UNION those of the ancestors it
     * inherits from, most-specific-wins per position.
     *
     * @return array<int, string>
     */
    public function effectiveManagerLabels(ProductCategory $category): array
    {
        return $this->mergeChain([...$this->ancestorChain($category), $category]);
    }

    /**
     * The labels $category would inherit from its ancestors ALONE — its own
     * assignments excluded — for the read-only "inherited from ancestors"
     * side list (mirrors CategoryHierarchy::ancestorAttributes()).
     *
     * @return array<int, string>
     */
    public function ancestorManagerLabels(ProductCategory $category): array
    {
        return $this->mergeChain($this->ancestorChain($category));
    }

    /**
     * Progressive per-position merge, root-first: a later (more specific)
     * level in $chain overrides an earlier one on the SAME position only,
     * leaving every other position from an earlier level untouched.
     *
     * @param  array<int, ProductCategory>  $chain
     * @return array<int, string>
     */
    private function mergeChain(array $chain): array
    {
        $merged = [];

        foreach ($chain as $level) {
            foreach ($this->ownLabels($level) as $position => $label) {
                $merged[$position] = $label;
            }
        }

        ksort($merged);

        return $merged;
    }

    /**
     * $category's ancestors it actually INHERITS manager labels from,
     * ROOT-FIRST (does not include $category itself) — the structural chain
     * truncated at the first node with `inherits_manager_labels` false (that
     * node's OWN labels still count, being a direct ancestor $category
     * inherits; the walk simply climbs no further). A category that itself
     * opts out returns an empty chain — its own labels are still applied by
     * effectiveManagerLabels() via the trailing `$category` push, nothing
     * from above is pulled in, and the barrier also stops any DESCENDANT of
     * $category from climbing past it.
     *
     * @return array<int, ProductCategory>
     */
    private function ancestorChain(ProductCategory $category): array
    {
        if (! $category->inherits_manager_labels) {
            return [];
        }

        $chain = [];
        $node = $category;
        $depth = 0;

        while ($node->parent_id !== null && $depth < self::MAX_DEPTH) {
            $parent = ProductCategory::find($node->parent_id);

            if ($parent === null) {
                break;
            }

            $chain[] = $parent;

            // Barrier: this ancestor contributes its own labels but, having
            // opted out, pulls nothing further up — stop climbing.
            if (! $parent->inherits_manager_labels) {
                break;
            }

            $node = $parent;
            $depth++;
        }

        return array_reverse($chain);
    }

    /**
     * $category's own `manager_labels`, keyed by INT position, with
     * empty/whitespace-only values excluded — write-side normalization
     * (ProductCategoryService) already guarantees this on persisted rows, but
     * a defensive re-check here keeps the read-side contract correct
     * regardless of how a row was written.
     *
     * @return array<int, string>
     */
    private function ownLabels(ProductCategory $category): array
    {
        $labels = [];

        foreach ((array) $category->manager_labels as $position => $label) {
            if (! is_string($label)) {
                continue;
            }

            $trimmed = trim($label);

            if ($trimmed === '') {
                continue;
            }

            $labels[(int) $position] = $trimmed;
        }

        return $labels;
    }
}
