<?php

namespace App\Services\ProductCategories;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;

/**
 * Resolves the `product-categories` for-select standard (spec 0023, ADR
 * 0011), mirroring SourceService::forSelect: query + scoping to a subtree
 * (spec 0077 INV-1) + meta enrichment (EFFECTIVE business function and
 * management mode), all batched via CategoryHierarchy — never a query or a
 * hierarchy walk per row. Extracted out of ProductCategoryService (spec
 * 0077) as its own semantic boundary: the for-select resolution is a single
 * responsibility distinct from the category CRUD/tree authority.
 */
class ProductCategoryForSelectResolver
{
    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * Minimal, searchable, paginated product-category list. Every returned
     * item carries its EFFECTIVE business function (spec 0040 BR-4) as
     * `meta.business_function`, resolved in ONE batched CategoryHierarchy
     * call.
     *
     * Amendment rev.3: when `$query->businessFunctionId` is set, the base
     * query is scoped to the ids whose EFFECTIVE business function matches
     * (same batched CategoryHierarchy call, no query per row) — additive,
     * identical behaviour when the param is absent.
     *
     * Spec 0077: every item ALSO carries `meta.root_category_id` and
     * `meta.management_mode` (the EFFECTIVE, inherited mode), resolved in
     * ONE batched CategoryHierarchy::rootManagementModesFor() call. When
     * `$query->rootCategoryId` is set, the base query is scoped to that
     * root's subtree (INV-1) via the already-batched `descendantIds()` —
     * additive, identical behaviour when the param is absent.
     */
    public function resolve(ForSelectQuery $query): ForSelectResult
    {
        // Spec 0074 D-4: this endpoint feeds DESTINATION pickers only (the
        // structural ones read /tree), so the selectable filter is
        // unconditional — no opt-in param a future consumer could forget.
        // `ids[]` hydration runs its own query below and stays exempt (D-3a),
        // so an already-associated category keeps resolving in edit mode.
        $base = ProductCategory::query()->select(['id', 'name'])->where('is_selectable', true);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        if ($query->businessFunctionId !== null) {
            $base->whereIn('id', $this->categoryIdsForBusinessFunction($query->businessFunctionId));
        }

        if ($query->rootCategoryId !== null) {
            $base->whereIn('id', $this->subtreeIds($query->rootCategoryId));
        }

        $total = (clone $base)->count();

        /** @var Collection<int, ProductCategory> $page */
        $page = $base->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedForSelectIds($page, $query);

        $this->attachEffectiveBusinessFunctionSummaries($items);
        $this->attachManagementModeSummaries($items);

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * $rootCategoryId itself PLUS every one of its descendants (spec 0077
     * INV-1 scoping) — a single batched CategoryHierarchy call, never a walk
     * per row.
     *
     * @return array<int, int>
     */
    private function subtreeIds(int $rootCategoryId): array
    {
        return array_merge([$rootCategoryId], $this->hierarchy->descendantIds($rootCategoryId));
    }

    /**
     * The BRANCH picker (spec 0092 D-4): the categories a quote-workflow
     * branch criterion may point at — those with at least one child.
     *
     * Deliberately NOT resolve() with a relaxed filter: that endpoint feeds
     * DESTINATION pickers and its `is_selectable` filter stays unconditional
     * (spec 0074 D-4). A branch criterion is about the opposite population —
     * the containers that filter hides, `Consulenza` first among them — so it
     * gets its own scope and, having no consumer for them, none of the
     * business-function / management-mode meta.
     */
    public function resolveBranches(ForSelectQuery $query): ForSelectResult
    {
        $base = ProductCategory::query()->select(['id', 'name'])->whereHas('children');

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, ProductCategory> $page */
        $page = $base->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        return new ForSelectResult(
            items: $this->appendHydratedForSelectIds($page, $query),
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * Every category id whose EFFECTIVE business function is
     * $businessFunctionId (spec 0040 amendment rev.3) — a single batched
     * CategoryHierarchy call, never a query per row.
     *
     * @return array<int, int>
     */
    private function categoryIdsForBusinessFunction(int $businessFunctionId): array
    {
        $summaries = $this->hierarchy->effectiveBusinessFunctionSummaries();

        return array_keys(array_filter(
            $summaries,
            static fn (?array $summary): bool => ($summary['id'] ?? null) === $businessFunctionId,
        ));
    }

    /**
     * Stash each item's EFFECTIVE business function {id, name}|null as the
     * `business_function_summary` attribute (read by
     * ProductCategoryForSelectResource), resolved in a single batched
     * CategoryHierarchy call — never one hierarchy walk per item.
     *
     * @param  Collection<int, ProductCategory>  $items
     */
    private function attachEffectiveBusinessFunctionSummaries(Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $summaries = $this->hierarchy->effectiveBusinessFunctionSummaries();

        foreach ($items as $item) {
            $item->setAttribute('business_function_summary', $summaries[$item->id] ?? null);
        }
    }

    /**
     * Stash each item's branch root {root_id, management_mode} (spec 0077)
     * as the `root_management_mode` attribute (read by
     * ProductCategoryForSelectResource as `meta.root_category_id` /
     * `meta.management_mode`), resolved in a single batched CategoryHierarchy
     * call — never one hierarchy walk per item.
     *
     * @param  Collection<int, ProductCategory>  $items
     */
    private function attachManagementModeSummaries(Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $modes = $this->hierarchy->rootManagementModesFor($items->pluck('id')->all());

        foreach ($items as $item) {
            $item->setAttribute('root_management_mode', $modes[$item->id] ?? null);
        }
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search AND the
     * selectable filter (spec 0074 D-3a — an edit form must still resolve the
     * label of a category that has since been made unselectable) and the same
     * id/name projection applies. Total is unaffected.
     *
     * @param  Collection<int, ProductCategory>  $page
     * @return Collection<int, ProductCategory>
     */
    private function appendHydratedForSelectIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, ProductCategory> $hydrated */
        $hydrated = ProductCategory::query()
            ->select(['id', 'name'])
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
