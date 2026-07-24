<?php

namespace App\Services\ProductCategories;

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;

/**
 * Read-side resolution of the product-category tree (spec 0017): ancestor
 * chains, a category's effective (own UNION inherited) attributes, the
 * ancestor-only attribute list used by the show endpoint, and the full
 * nested tree. Walks `parent_id` in PHP rather than a raw recursive SQL
 * query (correctness/portability over cleverness — works identically on the
 * SQLite dev/test driver and MySQL production).
 */
final class CategoryHierarchy
{
    /**
     * Defensive cap on the ancestor walk: the write-side anti-cycle guard
     * (ProductCategoryService) prevents a real cycle from ever being
     * persisted, so this only guards against corrupted data looping forever.
     */
    private const int MAX_DEPTH = 100;

    /**
     * $category's ancestors, ROOT-FIRST (does not include $category itself).
     * This is the STRUCTURAL walk — it ignores `inherits_attributes` and is
     * used only by the anti-cycle guard, which must see the full chain
     * regardless of any inheritance barrier.
     *
     * @return Collection<int, ProductCategory>
     */
    public function ancestors(ProductCategory $category): Collection
    {
        $chain = [];
        $currentId = $category->parent_id;
        $depth = 0;

        while ($currentId !== null && $depth < self::MAX_DEPTH) {
            $parent = ProductCategory::find($currentId);

            if ($parent === null) {
                break;
            }

            $chain[] = $parent;
            $currentId = $parent->parent_id;
            $depth++;
        }

        return collect(array_reverse($chain));
    }

    /**
     * The ancestors $category actually INHERITS from, ROOT-FIRST — the
     * structural walk truncated at the first inheritance barrier. A node whose
     * `inherits_attributes` is false does not pull in its own parent, so the
     * walk stops there: if $category itself opts out, this is empty; otherwise
     * it climbs while each node keeps inheriting, cutting off everything above
     * the first opted-out ancestor (that ancestor's OWN attributes still count,
     * as it is a direct ancestor $category inherits).
     *
     * @return Collection<int, ProductCategory>
     */
    private function inheritedAncestors(ProductCategory $category): Collection
    {
        if (! $category->inherits_attributes) {
            return collect();
        }

        $chain = [];
        $node = $category;
        $depth = 0;

        while ($node->parent_id !== null && $depth < self::MAX_DEPTH) {
            $parent = ProductCategory::find($node->parent_id);

            if ($parent === null) {
                break;
            }

            $chain[] = $parent;

            // Barrier: this ancestor contributes its own attributes but, having
            // opted out, pulls nothing further up — stop climbing.
            if (! $parent->inherits_attributes) {
                break;
            }

            $node = $parent;
            $depth++;
        }

        return collect(array_reverse($chain));
    }

    /**
     * Whether $candidateAncestorId is among $category's ancestors — the
     * anti-cycle check: reparenting $category under a node that descends
     * from $category would create a cycle.
     */
    public function isAncestorOf(ProductCategory $category, int $candidateAncestorId): bool
    {
        return $this->ancestors($category)->contains('id', $candidateAncestorId);
    }

    /**
     * $category's EFFECTIVE business function (spec 0023): its OWN
     * business_function_id when set, else the first one found walking
     * `parent_id` toward the root (inheritedBusinessFunctionFor) —
     * TRANSITIVE inheritance, unlike attributes, `inherits_attributes` is
     * NOT a barrier here. Null when neither $category nor any ancestor has
     * one.
     *
     * @return array{id: int, name: string, inherited: bool, source_category: array{id: int, name: string}|null}|null
     */
    public function effectiveBusinessFunction(ProductCategory $category): ?array
    {
        if ($category->business_function_id !== null) {
            return $this->businessFunctionSummary($category->business_function_id, inherited: false, source: null);
        }

        return $this->inheritedBusinessFunctionFor($category->parent_id);
    }

    /**
     * The business function a hypothetical child of $parentId would
     * INHERIT: $parentId's own business_function_id, else the first one
     * found walking further up. Used by the read-side
     * (effectiveBusinessFunction) AND by the write-side no-override guard
     * (ProductCategoryService), which must evaluate this against a
     * PROSPECTIVE parent before persisting.
     *
     * @return array{id: int, name: string, inherited: true, source_category: array{id: int, name: string}}|null
     */
    public function inheritedBusinessFunctionFor(?int $parentId): ?array
    {
        $currentId = $parentId;
        $depth = 0;

        while ($currentId !== null && $depth < self::MAX_DEPTH) {
            $node = ProductCategory::find($currentId);

            if ($node === null) {
                return null;
            }

            if ($node->business_function_id !== null) {
                return $this->businessFunctionSummary($node->business_function_id, inherited: true, source: $node);
            }

            $currentId = $node->parent_id;
            $depth++;
        }

        return null;
    }

    /**
     * Every DESCENDANT id of $categoryId (recursive, excludes itself), via a
     * single id/parent_id projection grouped in memory (mirrors tree()'s
     * $byParent index) — feeds the cascade-to-null write path
     * (ProductCategoryService), never a query per row.
     *
     * @return array<int, int>
     */
    public function descendantIds(int $categoryId): array
    {
        $byParent = ProductCategory::query()->select('id', 'parent_id')->get()->groupBy('parent_id');

        $ids = [];
        $visited = [];
        $queue = $byParent->get($categoryId, collect())->pluck('id')->all();

        while ($queue !== []) {
            $currentId = array_shift($queue);

            if (isset($visited[$currentId])) {
                continue;
            }

            $visited[$currentId] = true;
            $ids[] = $currentId;

            foreach ($byParent->get($currentId, collect()) as $child) {
                $queue[] = $child->id;
            }
        }

        return $ids;
    }

    /**
     * category id → EFFECTIVE business function NAME (or null), for every
     * category in one shot — the list/table read path (spec 0023 constraint:
     * never a query per row). Thin projection of effectiveBusinessFunctionSummaries().
     *
     * @return array<int, string|null>
     */
    public function effectiveBusinessFunctionNames(): array
    {
        return array_map(
            static fn (?array $summary): ?string => $summary['name'] ?? null,
            $this->effectiveBusinessFunctionSummaries(),
        );
    }

    /**
     * category id → EFFECTIVE business function {id, name} (or null), for
     * every category in one shot: two queries total (categories'
     * id/parent_id/business_function_id, then business_functions' id/name),
     * the rest resolved in memory — feeds the product-categories/for-select
     * `meta.business_function` (spec 0040) batched across a whole page,
     * never a query per row.
     *
     * @return array<int, array{id: int, name: string}|null>
     */
    public function effectiveBusinessFunctionSummaries(): array
    {
        $categories = ProductCategory::query()->select('id', 'parent_id', 'business_function_id')->get()->keyBy('id');
        $functions = BusinessFunction::query()->get(['id', 'name'])->keyBy('id');

        return $categories->keys()->mapWithKeys(
            fn (int $id): array => [$id => $this->walkForBusinessFunctionSummary($id, $categories, $functions)],
        )->all();
    }

    /**
     * @param  Collection<int, ProductCategory>  $categories
     * @param  Collection<int, BusinessFunction>  $functions
     * @return array{id: int, name: string}|null
     */
    private function walkForBusinessFunctionSummary(int $id, Collection $categories, Collection $functions): ?array
    {
        $currentId = $id;
        $depth = 0;

        while ($currentId !== null && $depth < self::MAX_DEPTH) {
            $category = $categories->get($currentId);

            if ($category === null) {
                return null;
            }

            if ($category->business_function_id !== null) {
                $function = $functions->get($category->business_function_id);

                return $function !== null ? ['id' => $function->id, 'name' => $function->name] : null;
            }

            $currentId = $category->parent_id;
            $depth++;
        }

        return null;
    }

    /**
     * @return array{id: int, name: string, inherited: bool, source_category: array{id: int, name: string}|null}|null
     */
    private function businessFunctionSummary(int $businessFunctionId, bool $inherited, ?ProductCategory $source): ?array
    {
        $function = BusinessFunction::find($businessFunctionId);

        if ($function === null) {
            return null;
        }

        return [
            'id' => $function->id,
            'name' => $function->name,
            'inherited' => $inherited,
            'source_category' => $source !== null ? ['id' => $source->id, 'name' => $source->name] : null,
        ];
    }

    /**
     * $category's EFFECTIVE attributes: its own assignments UNION those of the
     * ancestors it actually inherits from (see inheritedAncestors — the chain
     * is cut at the first `inherits_attributes = false` node), root-first
     * (AC-008). When the same attribute is assigned at
     * multiple levels, the MOST SPECIFIC one wins (own overrides an ancestor,
     * a closer ancestor overrides a farther one) — but its position in the
     * output stays where it FIRST appeared walking root→self, so the overall
     * order remains "ancestors first, then by sort_order" even after an
     * override.
     *
     * $context (spec 0061, default Opportunity so every pre-existing caller
     * is unaffected) scopes which pivot rows are read at EVERY level of the
     * chain — a category's Product and Opportunity attribute sets are
     * resolved and inherited completely independently of one another.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function effectiveAttributes(ProductCategory $category, AttributeContext $context = AttributeContext::Opportunity): Collection
    {
        $chain = $this->inheritedAncestors($category)->push($category);

        $ordered = [];
        $index = [];

        foreach ($chain as $level) {
            $isOwn = $level->is($category);

            foreach ($this->ownAttributeRows($level, $context) as $attribute) {
                $entry = [
                    'id' => $attribute->id,
                    'code' => $attribute->code,
                    'name' => $attribute->name,
                    'type' => $attribute->type,
                    'description' => $attribute->description,
                    'help_text' => $attribute->help_text,
                    'placeholder' => $attribute->placeholder,
                    'icon' => $attribute->icon,
                    'config' => $attribute->config,
                    'relation_target' => $attribute->relation_target,
                    'is_required' => (bool) $attribute->pivot->is_required,
                    'sort_order' => (int) $attribute->pivot->sort_order,
                    'inherited' => ! $isOwn,
                    'context' => $context->value,
                    'options' => $this->optionsFor($attribute),
                ];

                if (isset($index[$attribute->id])) {
                    $ordered[$index[$attribute->id]] = $entry;
                } else {
                    $ordered[] = $entry;
                    $index[$attribute->id] = array_key_last($ordered);
                }
            }
        }

        return collect(array_values($ordered));
    }

    /**
     * The attributes owned by the ANCESTORS $category inherits from (deduped, a
     * closer ancestor wins; empty when $category opts out of inheritance), for
     * the show endpoint's read-only `inherited_attributes` side list — never
     * merged with $category's own assignments. $context (spec 0061, default
     * Opportunity) scopes which pivot rows are read, same as
     * effectiveAttributes().
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function ancestorAttributes(ProductCategory $category, AttributeContext $context = AttributeContext::Opportunity): Collection
    {
        $ordered = [];
        $index = [];

        foreach ($this->inheritedAncestors($category) as $ancestor) {
            foreach ($this->ownAttributeRows($ancestor, $context) as $attribute) {
                $entry = [
                    'attribute_id' => $attribute->id,
                    'code' => $attribute->code,
                    'name' => $attribute->name,
                    'type' => $attribute->type,
                    'is_required' => (bool) $attribute->pivot->is_required,
                    'context' => $context->value,
                ];

                if (isset($index[$attribute->id])) {
                    $ordered[$index[$attribute->id]] = $entry;
                } else {
                    $ordered[] = $entry;
                    $index[$attribute->id] = array_key_last($ordered);
                }
            }
        }

        return collect(array_values($ordered));
    }

    /**
     * The full category tree, roots first, each node carrying its own
     * attributes/products counts (spec 0017 tree endpoint) and its OWN
     * business_function_id (spec 0023 REV — NOT the effective/inherited one:
     * the frontend resolves inheritance itself by walking this cached tree's
     * `parent_id` chain, so the write-side no-override 422 stays the sole
     * authority). No extra query: `get()` below already hydrates the full
     * row, business_function_id included.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tree(): array
    {
        $byParent = ProductCategory::query()
            ->withCount(['attributes', 'products'])
            ->orderBy('name')
            ->get()
            ->groupBy('parent_id');

        return $this->buildNodes($byParent, null);
    }

    /**
     * @param  Collection<int|string, Collection<int, ProductCategory>>  $byParent
     * @return array<int, array<string, mixed>>
     */
    private function buildNodes(Collection $byParent, ?int $parentId): array
    {
        $nodes = [];

        /** @var Collection<int, ProductCategory> $children */
        $children = $byParent->get($parentId ?? '', collect());

        foreach ($children as $category) {
            $nodes[] = [
                'id' => $category->id,
                'name' => $category->name,
                'parent_id' => $category->parent_id,
                'children' => $this->buildNodes($byParent, $category->id),
                'attributes_count' => (int) $category->attributes_count,
                'products_count' => (int) $category->products_count,
                'business_function_id' => $category->business_function_id,
            ];
        }

        return $nodes;
    }

    /**
     * $level's OWN attribute assignments in $context (pivot + attribute
     * eager-loaded), ordered by the pivot's sort_order.
     *
     * @return Collection<int, Attribute>
     */
    private function ownAttributeRows(ProductCategory $level, AttributeContext $context): Collection
    {
        return $level->attributes()
            ->wherePivot('context', $context->value)
            ->with('options')
            ->orderBy('attribute_category.sort_order')
            ->get();
    }

    /**
     * @return array<int, array{value: string, label: string, color: ?string, icon: ?string, sort_order: int, is_default: bool}>
     */
    private function optionsFor(Attribute $attribute): array
    {
        if ($attribute->type !== 'enum') {
            return [];
        }

        return $attribute->options->map(static fn ($option): array => [
            'value' => $option->value,
            'label' => $option->label,
            'color' => $option->color,
            'icon' => $option->icon,
            'sort_order' => $option->sort_order,
            'is_default' => $option->is_default,
        ])->all();
    }
}
