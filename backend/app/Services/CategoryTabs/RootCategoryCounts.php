<?php

declare(strict_types=1);

namespace App\Services\CategoryTabs;

use App\Products\ProductCategoryBranches;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Counts, per root product category, the DISTINCT records that sit on any
 * category of its branch (spec 0337, D-1). A record on two categories of the
 * same branch counts once, which a sum of the per-category counts cannot give,
 * so each root runs its own `count(distinct)` with the branch ids as bindings
 * (roots are a handful; the result is cached by the callers).
 */
final class RootCategoryCounts
{
    public function __construct(private readonly ProductCategoryBranches $branches) {}

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $scoped  joined on the category column and already narrowed to the actor's visibility
     * @return array<int, int> root id => distinct records, roots without records left out
     */
    public function count(Builder $scoped, string $categoryColumn, string $recordColumn): array
    {
        $counts = [];

        foreach ($this->branches->rootIds() as $rootId) {
            $count = (clone $scoped)
                ->whereIn($categoryColumn, $this->branches->branchIds($rootId))
                ->distinct()
                ->count($recordColumn);

            if ($count > 0) {
                $counts[$rootId] = $count;
            }
        }

        return $counts;
    }
}
