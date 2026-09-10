<?php

namespace App\Services;

use App\DataObjects\ProductCategories\CreateProductCategoryData;
use App\DataObjects\ProductCategories\UpdateProductCategoryData;
use App\Enums\AttributeContext;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\ProductCategories\CategoryManagerLabelResolver;
use App\Services\ProductCategories\CategoryTreeBuilder;
use App\Services\ProductCategories\RootOwnedSettingsWriter;
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
    public function __construct(
        private readonly CategoryHierarchy $hierarchy,
        private readonly CategoryTreeBuilder $treeBuilder,
        private readonly RootOwnedSettingsWriter $rootOwnedSettings,
        private readonly CategoryManagerLabelResolver $managerLabels,
    ) {}

    public function create(CreateProductCategoryData $data): ProductCategory
    {
        if ($data->businessFunctionId !== null) {
            $this->assertNoInheritedBusinessFunction($data->parentId);
        }

        $this->rootOwnedSettings->assertCreateNotOverridden($data);

        return DB::transaction(function () use ($data): ProductCategory {
            /** @var ProductCategory $category */
            $category = ProductCategory::create([
                'name' => $data->name,
                'parent_id' => $data->parentId,
                'inherits_product_attributes' => $data->inheritsProductAttributes,
                'inherits_quote_attributes' => $data->inheritsQuoteAttributes,
                'inherits_work_order_attributes' => $data->inheritsWorkOrderAttributes,
                'description' => $data->description,
                'business_function_id' => $data->businessFunctionId,
                'is_selectable' => $data->isSelectable,
                'manager_labels' => $this->normalizeManagerLabels($data->managerLabels),
                'inherits_manager_labels' => $data->inheritsManagerLabels,
                // The five ROOT-OWNED settings: a child never authors any of
                // them, it takes its branch root's value whatever was (or was
                // not) submitted (RootOwnedSettingsWriter).
                ...$this->rootOwnedSettings->resolvedColumnsFor($data),
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

        $this->rootOwnedSettings->assertUpdateNotOverridden($category, $data);

        return DB::transaction(function () use ($category, $data): ProductCategory {
            $attributes = $data->submittedAttributes();

            // Spec 0080: the label VALUES are normalized (trim, empty
            // removed, empty object -> null) here, never inside the DTO —
            // `submittedAttributes()` deliberately leaves `manager_labels`
            // out for exactly this reason.
            if ($data->managerLabelsSubmitted) {
                $attributes['manager_labels'] = $this->normalizeManagerLabels($data->managerLabels);
            }

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

            // The five ROOT-OWNED settings: only a reparent (the branch root
            // changed) or an edit of the setting itself can break the "whole
            // subtree mirrors its root" invariant.
            $this->rootOwnedSettings->syncSubtrees($category, $data);

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
     * @return array<int, array<string, mixed>>
     */
    public function tree(): array
    {
        return $this->treeBuilder->tree();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function effectiveAttributes(ProductCategory $category, AttributeContext $context): Collection
    {
        return $this->hierarchy->effectiveAttributes($category, $context);
    }

    /**
     * The category's inherited attributes across every context, each row
     * tagged `context` (spec 0061; spec 0084 replaces `opportunity` with
     * `quote`; spec 0098 adds `work_order`) — the config page's read-only
     * side list feeds the "Attributi Prodotto", "Attributi Offerta" AND
     * "Attributi Commessa" sections in one flat response, the frontend
     * splitting by that tag.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function inheritedAttributes(ProductCategory $category): Collection
    {
        return $this->hierarchy->ancestorAttributes($category, AttributeContext::Product)
            ->merge($this->hierarchy->ancestorAttributes($category, AttributeContext::Quote))
            ->merge($this->hierarchy->ancestorAttributes($category, AttributeContext::WorkOrder))
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
     * The ROOT each of $category's five root-owned settings is inherited FROM
     * (`requires_quote`, `management_mode`, `single_quote_per_opportunity`,
     * `generates_contract`, `simplified_offer_line`) — null on a root, which
     * owns its own values. The show endpoint spreads this straight into its
     * `meta` block as the read-only "inherited from X" hints; the values
     * themselves are real columns on $category, already carried by the
     * Resource.
     *
     * @return array<string, array{id: int, name: string}|null>
     */
    public function rootOwnedSourceCategories(ProductCategory $category): array
    {
        return $this->rootOwnedSettings->sourceCategories($category);
    }

    /**
     * $category's EFFECTIVE manager labels: its own UNION its inherited
     * ancestors', merged per position (spec 0080).
     *
     * @return array<int, string>
     */
    public function effectiveManagerLabels(ProductCategory $category): array
    {
        return $this->managerLabels->effectiveManagerLabels($category);
    }

    /**
     * The manager labels $category inherits from its ancestors ALONE — its
     * own assignments excluded (spec 0080), for the show endpoint's
     * read-only `inherited_manager_labels` side value, mirroring
     * `inherited_attributes`.
     *
     * @return array<int, string>
     */
    public function inheritedManagerLabels(ProductCategory $category): array
    {
        return $this->managerLabels->ancestorManagerLabels($category);
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

    /**
     * Normalizes a submitted `manager_labels` payload (spec 0080): trims
     * every value, drops non-string/empty-after-trim entries (never saved as
     * an empty string), and collapses an empty result to null (never an
     * empty JSON object) — the same "no own labels" representation the
     * column default and the resolver both expect.
     *
     * @param  array<int|string, mixed>|null  $labels
     * @return array<int, string>|null
     */
    private function normalizeManagerLabels(?array $labels): ?array
    {
        if ($labels === null) {
            return null;
        }

        $normalized = [];

        foreach ($labels as $position => $label) {
            if (! is_string($label)) {
                continue;
            }

            $trimmed = trim($label);

            if ($trimmed === '') {
                continue;
            }

            $normalized[(int) $position] = $trimmed;
        }

        return $normalized === [] ? null : $normalized;
    }
}
