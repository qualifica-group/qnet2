<?php

namespace App\Services\ProductCategories;

use App\Models\ProductCategory;

/**
 * Batch root-category resolution (spec 0132): the read-side card Resources'
 * `SummarizesProductLines` concern, and the request-management grid's
 * RequestRowMapper, need — for a record's (or a whole page's) product
 * lines — the ROOT category {id, name} each line's category hangs from.
 * Split out of CategoryHierarchy rather than grown into it (engineering.md
 * §6, file-size hard limit): reuses its memoized parentIdMap() for the
 * climb and memoizes every category's name on THIS instance too, so
 * rootSummariesFor() costs at most TWO queries total for its whole
 * lifetime — never a query per call, whether that is once per card record
 * or once per grid row of the same page (both resolve this class through
 * the container once per request/mapper, exactly like CategoryHierarchy
 * itself is consumed elsewhere in this domain).
 */
final class CategoryRootResolver
{
    /**
     * Mirrors CategoryHierarchy::MAX_DEPTH: guards the #76/#122/#179
     * cyclic-parent dirty data (spec 0132 context) so a self-referencing
     * `parent_id` returns null instead of looping forever.
     */
    private const int MAX_DEPTH = 100;

    /**
     * Memo of namesById()'s single projection query.
     *
     * @var array<int, string>|null
     */
    private ?array $namesById = null;

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * category id → ROOT category {id, name} (the ancestor with no
     * parent), for every requested id in one shot. A category that IS a
     * root maps to itself.
     *
     * @param  array<int, int>  $categoryIds
     * @return array<int, array{id: int, name: string}|null>
     */
    public function rootSummariesFor(array $categoryIds): array
    {
        $parentIdMap = $this->hierarchy->parentIdMap();
        $names = $this->namesById();

        $summaries = [];
        foreach (array_unique($categoryIds) as $categoryId) {
            $rootId = $this->walkToRootId($categoryId, $parentIdMap);
            $summaries[$categoryId] = $rootId !== null && isset($names[$rootId])
                ? ['id' => $rootId, 'name' => $names[$rootId]]
                : null;
        }

        return $summaries;
    }

    /**
     * @param  array<int, int|null>  $parentIdMap
     */
    private function walkToRootId(int $categoryId, array $parentIdMap): ?int
    {
        $currentId = $categoryId;
        $depth = 0;

        while ($depth < self::MAX_DEPTH) {
            if (! array_key_exists($currentId, $parentIdMap)) {
                return null;
            }

            $parentId = $parentIdMap[$currentId];

            if ($parentId === null) {
                return $currentId;
            }

            $currentId = $parentId;
            $depth++;
        }

        return null;
    }

    /**
     * Every category's name, id → name, read in ONE query and memoized on
     * this instance — mirrors CategoryHierarchy::categoriesById()'s own
     * whole-table read, the category count being small enough (spec 0132
     * context: ~200 rows) that this beats a `whereIn` per call once
     * rootSummariesFor() is invoked more than once in the same lifetime.
     *
     * @return array<int, string>
     */
    private function namesById(): array
    {
        return $this->namesById ??= ProductCategory::query()->pluck('name', 'id')->all();
    }
}
