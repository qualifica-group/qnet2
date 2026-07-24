<?php

namespace App\Services;

use App\DataObjects\ProductCategories\CreateProductCategoryData;
use App\DataObjects\ProductCategories\UpdateProductCategoryData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Enums\AttributeContext;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `product-categories` resource (spec 0017):
 * create/update (including the own-attributes full-replace sync and the
 * anti-cycle guard on `parent_id`), a restrictive delete, and the read-side
 * tree/effective-attributes/inherited-attributes views (delegated to
 * CategoryHierarchy). The controller stays thin; this Service is the single
 * authority.
 */
class ProductCategoryService
{
    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    public function create(CreateProductCategoryData $data): ProductCategory
    {
        if ($data->businessFunctionId !== null) {
            $this->assertNoInheritedBusinessFunction($data->parentId);
        }

        return DB::transaction(function () use ($data): ProductCategory {
            /** @var ProductCategory $category */
            $category = ProductCategory::create([
                'name' => $data->name,
                'parent_id' => $data->parentId,
                'inherits_attributes' => $data->inheritsAttributes,
                'description' => $data->description,
                'business_function_id' => $data->businessFunctionId,
            ]);

            if ($data->hasAttributes()) {
                $this->syncAttributes($category, $data->attributes);
            }

            // A freshly created category has no descendants yet, so no
            // cascade-to-null is possible here — cascade only ever applies
            // on update (see update()).
            return $category->fresh(['parent', 'attributes', 'businessFunction']);
        });
    }

    public function update(ProductCategory $category, UpdateProductCategoryData $data): ProductCategory
    {
        if ($data->hasParentId() && $data->parentId !== null) {
            $this->assertNoCycle($category, $data->parentId);
        }

        if ($data->businessFunctionIdSubmitted && $data->businessFunctionId !== null) {
            $resolvedParentId = $data->hasParentId() ? $data->parentId : $category->parent_id;
            $this->assertNoInheritedBusinessFunction($resolvedParentId);
        }

        return DB::transaction(function () use ($category, $data): ProductCategory {
            $attributes = $data->submittedAttributes();

            // Unconditional save: fire the model's saved event even when no native
            // attribute changed, so the HasCustomFields write pipeline (spec 0021)
            // persists a custom-fields-only edit. A clean save runs no UPDATE query.
            $category->fill($attributes)->save();

            if ($data->hasAttributes()) {
                $this->syncAttributes($category, $data->attributes);
            }

            // Only reparenting/business_function_id changes can disturb the
            // one-per-chain invariant (spec 0023) — skip the cascade check on
            // an unrelated edit (name/description/attributes-only).
            if ($data->hasParentId() || $data->businessFunctionIdSubmitted) {
                $this->cascadeBusinessFunctionToDescendants($category);
            }

            return $category->fresh(['parent', 'attributes', 'businessFunction']);
        });
    }

    /**
     * Restrictive delete: a category with child categories or associated
     * products cannot be removed (it would silently orphan them). Also
     * restrictive (spec 0040, BR-3) when referenced by at least one
     * opportunity.
     */
    public function delete(ProductCategory $category): void
    {
        if ($category->children()->exists() || $category->products()->exists()) {
            abort(409, 'This category has child categories or products and cannot be deleted.');
        }

        if ($category->opportunities()->exists()) {
            abort(409, 'This product category has opportunities and cannot be deleted.');
        }

        $category->delete();
    }

    /**
     * Minimal, searchable, paginated product-category list for the
     * for-select standard (spec 0023, ADR 0011), mirroring
     * SourceService::forSelect. Every returned item carries its EFFECTIVE
     * business function (spec 0040 BR-4) as `meta.business_function`,
     * resolved in ONE batched CategoryHierarchy call (never a query per row).
     *
     * Amendment rev.3: when `$query->businessFunctionId` is set, the base
     * query is scoped to the ids whose EFFECTIVE business function matches
     * (same batched CategoryHierarchy call, no query per row) — additive,
     * identical behaviour when the param is absent.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = ProductCategory::query()->select(['id', 'name']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        if ($query->businessFunctionId !== null) {
            $base->whereIn('id', $this->categoryIdsForBusinessFunction($query->businessFunctionId));
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

        return new ForSelectResult(
            items: $items,
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
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search and the same
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tree(): array
    {
        return $this->hierarchy->tree();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function effectiveAttributes(ProductCategory $category, AttributeContext $context = AttributeContext::Opportunity): Collection
    {
        return $this->hierarchy->effectiveAttributes($category, $context);
    }

    /**
     * The category's inherited attributes across BOTH contexts, each row
     * tagged `context` (spec 0061) — the config page's read-only side list
     * feeds both the "Attributi Prodotto" and "Attributi Opportunita'"
     * sections in one flat response, the frontend splitting by that tag.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function inheritedAttributes(ProductCategory $category): Collection
    {
        return $this->hierarchy->ancestorAttributes($category, AttributeContext::Opportunity)
            ->merge($this->hierarchy->ancestorAttributes($category, AttributeContext::Product))
            ->values();
    }

    /**
     * $category's EFFECTIVE business function (spec 0023): its own, or the
     * first ancestor's walking `parent_id` toward the root.
     *
     * @return array{id: int, name: string, inherited: bool, source_category: array{id: int, name: string}|null}|null
     */
    public function effectiveBusinessFunction(ProductCategory $category): ?array
    {
        return $this->hierarchy->effectiveBusinessFunction($category);
    }

    /**
     * $parentId may not be $category itself, nor one of its own descendants
     * (i.e. $category may not be an ancestor of the prospective new parent) —
     * either would create a cycle in the tree.
     */
    private function assertNoCycle(ProductCategory $category, int $parentId): void
    {
        if ($parentId === $category->id) {
            abort(422, 'A category cannot be its own parent.');
        }

        $parent = ProductCategory::find($parentId);

        if ($parent !== null && $this->hierarchy->isAncestorOf($parent, $category->id)) {
            abort(422, 'A category cannot be moved under one of its own descendants.');
        }
    }

    /**
     * NO-OVERRIDE guard (spec 0023): a category may not define its own
     * business function while it inherits one from $parentId's branch.
     */
    private function assertNoInheritedBusinessFunction(?int $parentId): void
    {
        if ($this->hierarchy->inheritedBusinessFunctionFor($parentId) !== null) {
            abort(422, 'This category inherits a business function from an ancestor and cannot define its own.');
        }
    }

    /**
     * CASCADE-TO-NULL (spec 0023): once $category owns an EFFECTIVE business
     * function (its own, or freshly inherited via a reparent), every
     * descendant's OWN business_function_id is cleared — the invariant
     * allows at most one non-null value per root→leaf chain. If $category
     * itself was moved under a branch that already provides one, its OWN
     * value is cleared too (the ancestor's wins over a now-orphaned own
     * value the no-override guard did not need to reject, since only
     * `parent_id` — not `business_function_id` — was submitted).
     */
    private function cascadeBusinessFunctionToDescendants(ProductCategory $category): void
    {
        $inherited = $this->hierarchy->inheritedBusinessFunctionFor($category->parent_id);

        if ($inherited !== null && $category->business_function_id !== null) {
            $category->update(['business_function_id' => null]);
        }

        $effectiveId = $category->business_function_id ?? $inherited['id'] ?? null;

        if ($effectiveId === null) {
            return;
        }

        $descendantIds = $this->hierarchy->descendantIds($category->id);

        if ($descendantIds !== []) {
            ProductCategory::whereIn('id', $descendantIds)->whereNotNull('business_function_id')->update(['business_function_id' => null]);
        }
    }

    /**
     * Full-replace sync of the category's OWN attribute assignments (pivot:
     * is_required/sort_order/context). Spec 0061: NOT Eloquent's
     * BelongsToMany::sync() — it keys by attribute_id only and cannot
     * represent the SAME attribute assigned to both the Product and
     * Opportunity sections (two pivot rows). Wholesale rewrite instead:
     * delete every row for this category, then insert the submitted set —
     * idempotent (re-submitting the same set yields the same rows) and
     * transaction-wrapped (a partial failure never leaves the category with
     * only its old rows deleted).
     *
     * @param  array<int, array{attribute_id: int, context: string, is_required?: bool, sort_order?: int}>  $attributes
     */
    private function syncAttributes(ProductCategory $category, array $attributes): void
    {
        DB::transaction(function () use ($category, $attributes): void {
            DB::table('attribute_category')->where('category_id', $category->id)->delete();

            if ($attributes === []) {
                return;
            }

            $now = now();

            $rows = array_map(static fn (array $row): array => [
                'attribute_id' => (int) $row['attribute_id'],
                'category_id' => $category->id,
                'context' => (string) $row['context'],
                'is_required' => (bool) ($row['is_required'] ?? false),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ], $attributes);

            DB::table('attribute_category')->insert($rows);
        });
    }
}
