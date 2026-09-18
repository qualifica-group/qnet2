<?php

declare(strict_types=1);

namespace App\Services\ProductCategories;

use App\Models\ProductCategory;
use Illuminate\Support\Collection;

/**
 * The EFFECTIVE "visible in the reports" flag of a product category (user
 * directive 2026-09-18). `is_reportable` is the node's OWN override: null =
 * inherit the parent's effective value (false at a root), true/false = forced
 * on this node. So a child of a reportable category is reportable by default
 * and can be forced off; a forced-off node passes "off" to its own subtree.
 *
 * Resolved at read time, never denormalised: the whole tree is one small
 * projection query and every consumer (report resolver, table, show endpoint)
 * already reads it in bulk.
 */
final class ReportableInheritance
{
    /**
     * Defensive cap on the ancestor walk, mirroring CategoryHierarchy's: the
     * write-side anti-cycle guard makes a real cycle impossible.
     */
    private const int MAX_DEPTH = 100;

    /**
     * Effective flag of every category in $categories (keyed by id, each
     * carrying `parent_id` and `is_reportable`), resolved top-down in memory.
     *
     * @param  Collection<int, ProductCategory>  $categories
     * @return array<int, bool>
     */
    public function effectiveMap(Collection $categories): array
    {
        $effective = [];

        foreach ($categories as $category) {
            $this->resolveInto($category, $categories, $effective);
        }

        return $effective;
    }

    /**
     * Effective flag of every category in the database.
     *
     * @return array<int, bool>
     */
    public function effectiveMapForAll(): array
    {
        return $this->effectiveMap(ProductCategory::query()->get(['id', 'parent_id', 'is_reportable'])->keyBy('id'));
    }

    /**
     * $category's effective flag plus the category it is inherited FROM — the
     * nearest ancestor carrying an override; null when $category overrides it
     * itself or nothing up the chain does (then the value is false).
     *
     * @return array{value: bool, source_category: array{id: int, name: string}|null}
     */
    public function resolve(ProductCategory $category): array
    {
        if ($category->is_reportable !== null) {
            return ['value' => $category->is_reportable, 'source_category' => null];
        }

        $parent = $category->parent_id !== null ? ProductCategory::find($category->parent_id) : null;
        $depth = 0;

        while ($parent !== null && $depth < self::MAX_DEPTH) {
            if ($parent->is_reportable !== null) {
                return [
                    'value' => $parent->is_reportable,
                    'source_category' => ['id' => $parent->id, 'name' => $parent->name],
                ];
            }

            $parent = $parent->parent_id !== null ? ProductCategory::find($parent->parent_id) : null;
            $depth++;
        }

        return ['value' => false, 'source_category' => null];
    }

    /**
     * @param  Collection<int, ProductCategory>  $categories
     * @param  array<int, bool>  $effective
     */
    private function resolveInto(ProductCategory $category, Collection $categories, array &$effective): void
    {
        // Step 1: climb until a node whose value is known (own override or
        // already resolved), remembering the inheriting nodes on the way.
        $pending = [];
        $node = $category;
        $value = false;

        while ($node !== null && count($pending) < self::MAX_DEPTH) {
            if (isset($effective[$node->id])) {
                $value = $effective[$node->id];

                break;
            }

            if ($node->is_reportable !== null) {
                $value = $node->is_reportable;
                $effective[$node->id] = $value;

                break;
            }

            $pending[] = $node->id;
            $node = $categories->get($node->parent_id);
        }

        // Step 2: every inheriting node on the path takes that value.
        foreach ($pending as $id) {
            $effective[$id] = $value;
        }
    }
}
