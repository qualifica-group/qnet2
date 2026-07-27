<?php

namespace App\Services\ProductCategories;

use App\DataObjects\ProductCategories\UpdateProductCategoryData;
use App\Exceptions\ProductCategories\BulkMoveConflictException;
use App\Models\ProductCategory;
use App\Services\ProductCategoryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bulk reparenting of product categories (spec 0063): move many categories
 * under one new parent — or to the root — in a single operation.
 *
 * All-or-nothing (decision D-3): every rule is evaluated against the WHOLE
 * batch before a single row is written, and the first reason that yields
 * conflicts aborts the operation with the complete list of offending rows.
 * A nested selection (a selected category descending from another selected
 * one) is refused rather than silently pruned or flattened (decision D-2).
 *
 * The actual write goes through ProductCategoryService::update() row by row,
 * so the existing reparenting semantics stay the single authority: the
 * business-function cascade to descendants (spec 0023) runs unchanged, and
 * each move is recorded by the model's activity log — which a bulk
 * `whereIn()->update()` would silently skip.
 *
 * Lives in its own class rather than on ProductCategoryService, which is
 * already past the 300-line soft limit (engineering.md §6).
 */
final class BulkMoveCategories
{
    public function __construct(
        private readonly CategoryHierarchy $hierarchy,
        private readonly ProductCategoryService $service,
    ) {}

    /**
     * Moves every category in $categoryIds under $parentId (null = root) and
     * returns how many were ACTUALLY reparented — a category already sitting
     * under the target is a no-op and is not counted.
     *
     * @param  array<int, int>  $categoryIds
     *
     * @throws BulkMoveConflictException when the batch violates a rule (nothing is written)
     */
    public function handle(array $categoryIds, ?int $parentId): int
    {
        // Step 1: load the targeted rows and the ancestor chains needed by
        // every check below (one projection query, see ancestorMap()).
        $categories = ProductCategory::query()->whereIn('id', $categoryIds)->orderBy('name')->get();
        $selectedIds = $categories->pluck('id')->all();
        $ancestors = $this->ancestorChains(array_merge($selectedIds, $parentId !== null ? [$parentId] : []));

        // Step 2: validate the whole batch BEFORE any write (D-3).
        $this->assertParentNotSelected($categories, $parentId);
        $this->assertNoNestedSelection($categories, $selectedIds, $ancestors);
        $this->assertNoCycle($categories, $selectedIds, $ancestors, $parentId);
        $this->assertNoBusinessFunctionConflict($categories, $parentId);

        // Step 3: write. No guard can fire from here on, so the transaction
        // only has to protect against an infrastructure failure mid-batch.
        return DB::transaction(function () use ($categories, $parentId): int {
            $moved = 0;

            foreach ($categories as $category) {
                if ($category->parent_id === $parentId) {
                    continue;
                }

                $this->service->update($category, new UpdateProductCategoryData(
                    parentId: $parentId,
                    parentIdSubmitted: true,
                ));

                $moved++;
            }

            return $moved;
        });
    }

    /**
     * The target cannot be one of the moved categories (a category cannot be
     * its own parent).
     *
     * @param  Collection<int, ProductCategory>  $categories
     */
    private function assertParentNotSelected(Collection $categories, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $selected = $categories->firstWhere('id', $parentId);

        if ($selected === null) {
            return;
        }

        $this->reject('self_parent', [$this->conflict($selected, 'It is the destination category itself.')],
            'A category cannot be moved under itself.');
    }

    /**
     * Nested selection (D-2): a selected category that descends from another
     * selected one is refused, so the user fixes the selection instead of the
     * batch silently flattening or dropping part of the tree.
     *
     * @param  Collection<int, ProductCategory>  $categories
     * @param  array<int, int>  $selectedIds
     * @param  array<int, array<int, int>>  $ancestors
     */
    private function assertNoNestedSelection(Collection $categories, array $selectedIds, array $ancestors): void
    {
        $conflicts = [];

        foreach ($categories as $category) {
            $selectedAncestorId = $this->firstIntersection($ancestors[$category->id] ?? [], $selectedIds);

            if ($selectedAncestorId !== null) {
                $ancestor = $categories->firstWhere('id', $selectedAncestorId);
                $conflicts[] = $this->conflict($category, sprintf(
                    'It already descends from the selected category "%s".',
                    $ancestor?->name ?? (string) $selectedAncestorId,
                ));
            }
        }

        if ($conflicts !== []) {
            $this->reject('nested_selection', $conflicts,
                'The selection contains categories nested inside one another.');
        }
    }

    /**
     * Anti-cycle: the destination may not descend from any moved category —
     * equivalently, none of the moved categories may appear in the target's
     * own ancestor chain.
     *
     * @param  Collection<int, ProductCategory>  $categories
     * @param  array<int, int>  $selectedIds
     * @param  array<int, array<int, int>>  $ancestors
     */
    private function assertNoCycle(Collection $categories, array $selectedIds, array $ancestors, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $conflicts = collect($ancestors[$parentId] ?? [])
            ->intersect($selectedIds)
            ->map(fn (int $id): ?ProductCategory => $categories->firstWhere('id', $id))
            ->filter()
            ->map(fn (ProductCategory $category): array => $this->conflict(
                $category,
                'The destination category descends from it.',
            ))
            ->values()
            ->all();

        if ($conflicts !== []) {
            $this->reject('cycle', $conflicts,
                'A category cannot be moved under one of its own descendants.');
        }
    }

    /**
     * No-override guard (spec 0023): a category keeping its OWN business
     * function may not land under a chain that already provides one. The
     * single-row path would silently clear it (the cascade); in bulk the user
     * asked to be told instead (D-3).
     *
     * @param  Collection<int, ProductCategory>  $categories
     */
    private function assertNoBusinessFunctionConflict(Collection $categories, ?int $parentId): void
    {
        // Same destination for the whole batch, so the inherited function is
        // resolved once rather than per row.
        $inherited = $this->hierarchy->inheritedBusinessFunctionFor($parentId);

        if ($inherited === null) {
            return;
        }

        $conflicts = $categories
            ->filter(fn (ProductCategory $category): bool => $category->business_function_id !== null)
            ->map(fn (ProductCategory $category): array => $this->conflict($category, sprintf(
                'It has its own business function, but the destination already inherits "%s".',
                $inherited['name'],
            )))
            ->values()
            ->all();

        if ($conflicts !== []) {
            $this->reject('business_function_conflict', $conflicts,
                'Some categories would override the business function inherited from the destination.');
        }
    }

    /**
     * category id → its ancestor ids, resolved for MANY nodes out of a single
     * id/parent_id projection (mirrors CategoryHierarchy::descendantIds()'s
     * one-query approach). The per-node walkers on CategoryHierarchy issue a
     * query per level, which a batch of N categories would multiply by N.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<int, int>>
     */
    private function ancestorChains(array $ids): array
    {
        $parents = ProductCategory::query()->pluck('parent_id', 'id');
        $chains = [];

        foreach ($ids as $id) {
            $chain = [];
            $currentId = $parents[$id] ?? null;

            // The persisted tree cannot contain a cycle (the write-side guards
            // prevent it); the visited set only stops corrupted data looping.
            while ($currentId !== null && ! in_array($currentId, $chain, true)) {
                $chain[] = (int) $currentId;
                $currentId = $parents[$currentId] ?? null;
            }

            $chains[$id] = $chain;
        }

        return $chains;
    }

    /**
     * @param  array<int, int>  $chain
     * @param  array<int, int>  $selectedIds
     */
    private function firstIntersection(array $chain, array $selectedIds): ?int
    {
        foreach ($chain as $ancestorId) {
            if (in_array($ancestorId, $selectedIds, true)) {
                return $ancestorId;
            }
        }

        return null;
    }

    /**
     * @return array{id: int, name: string, detail: string}
     */
    private function conflict(ProductCategory $category, string $detail): array
    {
        return ['id' => $category->id, 'name' => $category->name, 'detail' => $detail];
    }

    /**
     * @param  array<int, array{id: int, name: string, detail: string}>  $conflicts
     *
     * @throws BulkMoveConflictException
     */
    private function reject(string $reason, array $conflicts, string $message): void
    {
        throw new BulkMoveConflictException($reason, $conflicts, $message);
    }
}
