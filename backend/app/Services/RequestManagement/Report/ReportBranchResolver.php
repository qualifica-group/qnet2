<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use App\Models\ProductCategory;
use Illuminate\Support\Collection;

/**
 * Resolves the report branches (spec 0106, made dynamic by spec 0131): one
 * branch per product category flagged `is_reportable`, ordered by name, keyed
 * by its id, each expanded to its own full subtree — so a reportable parent
 * and a reportable child both appear, the parent's row covering the child's
 * requests too.
 *
 * The whole tree is read in ONE projection query and every subtree is walked
 * in memory: the same BFS as CategoryHierarchy::descendantIds(), which would
 * re-read the table once per branch. resolve() is meant to be called ONCE
 * per request by the caller — never re-resolved per row.
 */
final class ReportBranchResolver
{
    /**
     * @return array<int, ReportBranch>
     */
    public function resolve(): array
    {
        // Step 1: the whole tree, as parent_id => child ids.
        $categories = ProductCategory::query()->get(['id', 'parent_id', 'name', 'is_reportable']);
        $childIdsByParent = $categories->groupBy('parent_id')->map(static fn (Collection $children): array => $children->pluck('id')->all());

        // Step 2: one branch per reportable category, root + every descendant.
        return $categories
            ->filter(static fn (ProductCategory $category): bool => $category->is_reportable)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (ProductCategory $category): ReportBranch => new ReportBranch(
                key: (string) $category->id,
                label: $category->name,
                categoryIds: [$category->id, ...$this->descendantIds($category->id, $childIdsByParent)],
            ))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int|string, array<int, int>>  $childIdsByParent
     * @return array<int, int>
     */
    private function descendantIds(int $rootId, Collection $childIdsByParent): array
    {
        $ids = [];
        $visited = [$rootId => true];
        $queue = $childIdsByParent->get($rootId, []);

        while ($queue !== []) {
            $currentId = array_shift($queue);

            if (isset($visited[$currentId])) {
                continue;
            }

            $visited[$currentId] = true;
            $ids[] = $currentId;
            array_push($queue, ...$childIdsByParent->get($currentId, []));
        }

        return $ids;
    }

    /**
     * The branch keys an actor may submit — the allow-list `category_keys.*`
     * is validated against (backend.md §8).
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_map(static fn (ReportBranch $branch): string => $branch->key, $this->resolve());
    }
}
