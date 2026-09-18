<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use App\Models\ProductCategory;
use App\Services\ProductCategories\ReportableInheritance;
use Illuminate\Support\Collection;

/**
 * Resolves the report branches (spec 0106, made dynamic by spec 0131): one
 * branch per EFFECTIVELY reportable product category (ReportableInheritance:
 * a child inherits its parent's flag unless it forces its own — user
 * directive 2026-09-18), in tree order — each level sorted by name — keyed by
 * the category id and expanded to its own reportable subtree, so a parent's
 * row covers its reportable children's requests too. A child forced off is
 * left out with its subtree, from its own row AND from its ancestors' rows.
 *
 * A category takes the active columns of its nearest ancestor-or-self named
 * in `config('request-management-report.category_columns')` — walking the
 * whole tree, so a report root under a non-reportable mapped parent (e.g.
 * "Orientamento Specialistico" under "APL") keeps its parent's columns.
 *
 * The whole tree is read in ONE projection query and every subtree is walked
 * in memory. resolve() is meant to be called ONCE per request by the caller —
 * never re-resolved per row.
 */
final class ReportBranchResolver
{
    public function __construct(private readonly ReportableInheritance $reportable) {}

    /**
     * @return array<int, ReportBranch>
     */
    public function resolve(): array
    {
        // Step 1: the whole tree, indexed by id and by parent, and the
        // effective report flag of every node.
        $categories = ProductCategory::query()->get(['id', 'parent_id', 'name', 'is_reportable'])->keyBy('id');
        $reportable = $this->reportable->effectiveMap($categories);
        $childrenByParent = $categories
            ->filter(fn (ProductCategory $category): bool => $reportable[$category->id])
            ->groupBy('parent_id');

        // Step 2: the roots — reportable categories whose parent is not (a
        // reportable child is reached by the tree walk below anyway).
        $roots = $categories
            ->filter(fn (ProductCategory $category): bool => $reportable[$category->id] && ! ($reportable[$category->parent_id] ?? false))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);

        // Step 3: each root, then its reportable subtree depth-first.
        $branches = [];
        foreach ($roots as $root) {
            $this->appendSubtree($root, 0, null, $this->inheritedColumns($root, $categories), $childrenByParent, $branches);
        }

        return $branches;
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

    /**
     * @param  array<int, string>  $inheritedColumns
     * @param  Collection<int|string, Collection<int, ProductCategory>>  $childrenByParent  reportable categories only
     * @param  array<int, ReportBranch>  $branches
     */
    private function appendSubtree(ProductCategory $category, int $depth, ?string $parentKey, array $inheritedColumns, Collection $childrenByParent, array &$branches): void
    {
        $columns = $this->activeColumns($category->name) ?? $inheritedColumns;

        $branches[] = ReportBranch::withColumns(
            key: (string) $category->id,
            label: $category->name,
            categoryIds: [$category->id, ...$this->descendantIds($category->id, $childrenByParent)],
            columns: $columns,
            depth: $depth,
            parentKey: $parentKey,
        );

        $children = $childrenByParent->get($category->id, collect())->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($children as $child) {
            $this->appendSubtree($child, $depth + 1, (string) $category->id, $columns, $childrenByParent, $branches);
        }
    }

    /**
     * The active columns a report root inherits from its nearest mapped
     * ANCESTOR (structural walk, reportable or not); [] when none is mapped.
     *
     * @param  Collection<int, ProductCategory>  $categories  keyed by id
     * @return array<int, string>
     */
    private function inheritedColumns(ProductCategory $root, Collection $categories): array
    {
        $visited = [$root->id => true];
        $ancestor = $categories->get($root->parent_id);

        while ($ancestor !== null && ! isset($visited[$ancestor->id])) {
            $columns = $this->activeColumns($ancestor->name);

            if ($columns !== null) {
                return $columns;
            }

            $visited[$ancestor->id] = true;
            $ancestor = $categories->get($ancestor->parent_id);
        }

        return [];
    }

    /**
     * The indicator columns mapped to a category NAME (user directive
     * 2026-09-18), case-insensitive; null when the name is not in the map.
     *
     * @return array<int, string>|null
     */
    private function activeColumns(string $categoryName): ?array
    {
        $map = (array) config('request-management-report.category_columns');
        $columns = $map[mb_strtolower(trim($categoryName))] ?? null;

        return $columns === null ? null : array_values((array) $columns);
    }

    /**
     * @param  Collection<int|string, Collection<int, ProductCategory>>  $childrenByParent
     * @return array<int, int>
     */
    private function descendantIds(int $rootId, Collection $childrenByParent): array
    {
        $ids = [];
        $visited = [$rootId => true];
        $queue = $childrenByParent->get($rootId, collect())->pluck('id')->all();

        while ($queue !== []) {
            $currentId = array_shift($queue);

            if (isset($visited[$currentId])) {
                continue;
            }

            $visited[$currentId] = true;
            $ids[] = $currentId;
            array_push($queue, ...$childrenByParent->get($currentId, collect())->pluck('id')->all());
        }

        return $ids;
    }
}
