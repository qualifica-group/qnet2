<?php

declare(strict_types=1);

namespace App\Products;

use App\Models\ProductCategory;

/**
 * Read-side view of the product-category tree as branches (spec 0337, D-1): a
 * root (`parent_id` null) and all its descendants at any depth. The whole tree
 * is read once per instance with a single `id, parent_id` projection, so a
 * request that asks many questions never queries per node.
 */
final class ProductCategoryBranches
{
    /** Defensive cap on the upward walk: a persisted cycle is prevented on write, this only guards corrupt data. */
    private const int MAX_DEPTH = 100;

    /**
     * @var array<int, int|null>|null
     */
    private ?array $parentById = null;

    /**
     * Every root id, ascending.
     *
     * @return array<int, int>
     */
    public function rootIds(): array
    {
        $roots = array_keys(array_filter($this->parentById(), static fn (?int $parentId): bool => $parentId === null));
        sort($roots);

        return $roots;
    }

    /**
     * The root and all its descendants. A non-root id yields just its own subtree.
     *
     * @return array<int, int>
     */
    public function branchIds(int $rootId): array
    {
        $childrenByParent = [];

        foreach ($this->parentById() as $id => $parentId) {
            if ($parentId !== null) {
                $childrenByParent[$parentId][] = $id;
            }
        }

        $ids = [$rootId];

        // The list grows while it is walked (a foreach would iterate a copy).
        for ($index = 0; $index < count($ids); $index++) {
            foreach ($childrenByParent[$ids[$index]] ?? [] as $childId) {
                $ids[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * The root of the branch $categoryId belongs to (itself when it is a root).
     */
    public function rootIdOf(int $categoryId): int
    {
        $parents = $this->parentById();
        $currentId = $categoryId;

        for ($depth = 0; $depth < self::MAX_DEPTH; $depth++) {
            $parentId = $parents[$currentId] ?? null;

            if ($parentId === null) {
                return $currentId;
            }

            $currentId = $parentId;
        }

        return $currentId;
    }

    /**
     * @return array<int, int|null>
     */
    private function parentById(): array
    {
        return $this->parentById ??= ProductCategory::query()
            ->select('id', 'parent_id')
            ->get()
            ->mapWithKeys(static fn (ProductCategory $category): array => [
                (int) $category->id => $category->parent_id === null ? null : (int) $category->parent_id,
            ])
            ->all();
    }
}
