<?php

namespace App\Services\ProductCategories;

use App\Models\ProductCategory;

/**
 * The single authority on the EFFECTIVE activity of a product category
 * (spec 0208 D-1/D-6): a category is effectively inactive when its own
 * `is_active` is false OR any ancestor's is. The own flag of the descendants
 * is never rewritten; the cascade is resolved here, in batch: one query for
 * the own-inactive ids plus the memoized CategoryHierarchy::parentIdMap(),
 * never a query or a walk per row. Kept out of CategoryHierarchy (size limit).
 */
final class CategoryActivity
{
    /** @var array<int, int>|null */
    private ?array $inactiveIds = null;

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * Ids of every effectively inactive category (own-inactive plus all their
     * descendants), memoized per instance.
     *
     * @return array<int, int>
     */
    public function inactiveCategoryIds(): array
    {
        return $this->inactiveIds ??= $this->resolveInactiveIds();
    }

    public function isActive(int $categoryId): bool
    {
        return ! in_array($categoryId, $this->inactiveCategoryIds(), true);
    }

    /**
     * @return array<int, int>
     */
    private function resolveInactiveIds(): array
    {
        $ownInactive = ProductCategory::query()
            ->where('is_active', false)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($ownInactive === []) {
            return [];
        }

        $childrenByParent = [];
        foreach ($this->hierarchy->parentIdMap() as $id => $parentId) {
            if ($parentId !== null) {
                $childrenByParent[$parentId][] = $id;
            }
        }

        // Breadth-first over the in-memory adjacency; `$seen` also guards a
        // corrupted cycle from looping.
        $seen = array_fill_keys($ownInactive, true);
        $queue = $ownInactive;
        while ($queue !== []) {
            $current = array_pop($queue);
            foreach ($childrenByParent[$current] ?? [] as $childId) {
                if (! isset($seen[$childId])) {
                    $seen[$childId] = true;
                    $queue[] = $childId;
                }
            }
        }

        return array_keys($seen);
    }
}
