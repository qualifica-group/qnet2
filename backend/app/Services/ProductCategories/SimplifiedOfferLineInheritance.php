<?php

namespace App\Services\ProductCategories;

use App\Models\ProductCategory;
use Illuminate\Support\Collection;

/**
 * Single authority on the `simplified_offer_line` rule of the product-category
 * tree (spec 0114, revised by spec 0188): when true, an offer line written in
 * Gestione Richieste under this branch drops the quantity/unit-price/VAT
 * controls and the server freezes those values from the picked product.
 *
 * Nearest-ancestor semantics: a root declares the rule through
 * `simplified_offer_line`; a child may declare its own through
 * `simplified_offer_line_override` (null = inherit). The EFFECTIVE value of
 * every node stays DENORMALISED in `simplified_offer_line`, so every reader
 * (normalizer, grid, filters, export) reads a real column with no walk. This
 * class is the only writer of that column outside a root's own edit.
 *
 * The flag governs the OFFER-LINE UI/write-shape in Gestione Richieste only
 * (0114 D-3): the Offerte module never reads it.
 */
final class SimplifiedOfferLineInheritance
{
    private const string COLUMN = 'simplified_offer_line';

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * The value a child of $parentId INHERITS — the parent's effective value.
     * Null means "nothing to inherit" (no parent, or the row is gone).
     */
    public function inheritedValueFor(?int $parentId): ?bool
    {
        if ($parentId === null) {
            return null;
        }

        $parent = ProductCategory::find($parentId);

        return $parent === null ? null : (bool) $parent->simplified_offer_line;
    }

    /**
     * The nearest ancestor that DECLARES the value (an override or the root),
     * for the read-only "inherited from X" hint. Null when $category declares
     * it itself (a root, or a child with its own override).
     *
     * @return array{id: int, name: string}|null
     */
    public function sourceCategoryFor(ProductCategory $category): ?array
    {
        if ($category->parent_id === null || $category->simplified_offer_line_override !== null) {
            return null;
        }

        $source = $this->hierarchy->ancestors($category)
            ->reverse()
            ->first(fn (ProductCategory $ancestor): bool => $ancestor->parent_id === null || $ancestor->simplified_offer_line_override !== null);

        return $source !== null ? ['id' => $source->id, 'name' => $source->name] : null;
    }

    /**
     * Re-aligns $category and its subtree on the effective value, top-down:
     * each node takes its own override, else its parent's effective value, so
     * an override node keeps its value and feeds its own descendants.
     * Idempotent — rows already aligned are left untouched (no UPDATE, no
     * activity-log noise).
     */
    public function syncSubtree(ProductCategory $category): void
    {
        // Step 1: the node itself.
        $effective = $this->effectiveOf($category);

        if ((bool) $category->simplified_offer_line !== $effective) {
            $category->update([self::COLUMN => $effective]);
        }

        // Step 2: the subtree, resolved in memory from one projection query.
        $byParent = ProductCategory::query()
            ->get(['id', 'parent_id', self::COLUMN, 'simplified_offer_line_override'])
            ->groupBy('parent_id');
        $stale = $this->collectStale($byParent, $category->id, $effective);

        // Step 3: one mass UPDATE per target value.
        foreach ([true, false] as $value) {
            if ($stale[(int) $value] !== []) {
                ProductCategory::whereIn('id', $stale[(int) $value])->update([self::COLUMN => $value]);
            }
        }
    }

    /** A root's own column, else the node's override, else the parent's effective value. */
    private function effectiveOf(ProductCategory $category): bool
    {
        if ($category->parent_id === null) {
            return (bool) $category->simplified_offer_line;
        }

        return $category->simplified_offer_line_override
            ?? $this->inheritedValueFor($category->parent_id)
            ?? (bool) $category->simplified_offer_line;
    }

    /**
     * Ids under $parentId whose stored value differs from the one they must
     * hold, keyed by the target value (0 = false, 1 = true).
     *
     * @param  Collection<int|string, Collection<int, ProductCategory>>  $byParent
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    private function collectStale(Collection $byParent, int $parentId, bool $parentEffective): array
    {
        $stale = [0 => [], 1 => []];
        $queue = [[$parentId, $parentEffective]];

        while ($queue !== []) {
            [$currentId, $currentEffective] = array_shift($queue);

            foreach ($byParent->get($currentId, collect()) as $child) {
                $effective = $child->simplified_offer_line_override ?? $currentEffective;

                if ((bool) $child->simplified_offer_line !== $effective) {
                    $stale[(int) $effective][] = $child->id;
                }

                $queue[] = [$child->id, $effective];
            }
        }

        return $stale;
    }
}
