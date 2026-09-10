<?php

declare(strict_types=1);

namespace App\Services\ProductCategories;

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Enums\LayoutFormScope;
use App\Enums\LayoutItemWidth;
use App\Enums\LayoutSectionVariant;
use App\Models\AttributeLayout;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Read/write for a category's configured layout, one row per (category,
 * context, scope) — spec 0062, D1/D3 revised: a scope is either the SHARED
 * `all` layout or a per-mode override of it (App\Enums\LayoutFormScope).
 * Authoring (the configurator) always addresses one exact scope
 * (`resolveExact`/`upsert`); every CONSUMPTION path — Product form/detail
 * and each contributing category of an Opportunity/Quote merge
 * (App\RequestManagement\AttributeLayoutMerger) — resolves a concrete
 * FormMode through `resolveWithFallback`.
 *
 * Reading falls back along TWO axes (spec 0115): the scope one above, and the
 * CATEGORY one — a category with no layout of its own renders its nearest
 * inheriting ancestor's, under the same per-context barrier that governs
 * attribute assignments. See resolveInherited().
 */
final class AttributeLayoutService
{
    public function __construct(
        private readonly AttributeLayoutValidator $validator,
        private readonly CategoryHierarchy $hierarchy,
    ) {}

    /**
     * The persisted, raw blob for the EXACT (category, context, scope) — or
     * null when that specific row is not configured. The configurator's
     * authoring load, which must distinguish "this mode has its own
     * override" from "this mode inherits the shared layout" and can never
     * see one as the other.
     *
     * @return array{sections: array<int, array<string, mixed>>}|null
     */
    public function resolveExact(ProductCategory $category, AttributeContext $context, LayoutFormScope $scope): ?array
    {
        return $this->find($category, $context, $scope)?->layout;
    }

    /**
     * The layout to RENDER for (category, context, form_mode) — see
     * resolveInherited(), of which this is the layout-only projection every
     * consuming path uses.
     *
     * @return array{sections: array<int, array<string, mixed>>}|null
     */
    public function resolveWithFallback(ProductCategory $category, AttributeContext $context, FormMode $formMode): ?array
    {
        return $this->resolveInherited($category, $context, $formMode)[0];
    }

    /**
     * The ancestor's layout $category would render for the AUTHORED scope,
     * and that ancestor — the configurator's "this category inherits the
     * layout of X" banner (spec 0115, AC-010). Both null when nothing is
     * inherited: no ancestry, a barrier, or no ancestor with a layout.
     *
     * Addressed by LayoutFormScope, not FormMode: authoring edits one scope,
     * and `all` — the shared layout, which is a scope but never a render-time
     * mode — is the one it edits by default.
     *
     * @return array{0: array{sections: array<int, array<string, mixed>>}|null, 1: ProductCategory|null}
     */
    public function resolveInheritedForScope(ProductCategory $category, AttributeContext $context, LayoutFormScope $scope): array
    {
        $ancestors = $this->hierarchy->inheritedAncestors($category, $context)->reverse()->all();

        return $this->firstConfigured($ancestors, $context, self::scopeCandidates($scope), $category);
    }

    /**
     * The layout to render, AND the ancestor it came from when $category has
     * none of its own (spec 0115) — the configurator needs to name that
     * ancestor, every other caller only wants the blob.
     *
     * TWO axes of fallback, with the CATEGORY as the OUTER one (D-2): at each
     * level the mode's own override wins over the shared `all` layout, and
     * only a level offering NEITHER hands the question to its parent. So a
     * category's own shared layout beats an ancestor's per-mode override — the
     * most specific category always wins, exactly as it does for attribute
     * assignments.
     *
     * The chain is CategoryHierarchy's barrier-aware one (D-1): a category
     * that opts out of inheriting $context's attributes inherits no layout in
     * $context either. That is what keeps a category with a field set of its
     * own — "DIL" in the client catalogue — from rendering its parent's form.
     *
     * Null layout means no level had one: flat rendering, spec 0062 AC-007.
     * The source is null both then and when $category answered for itself.
     *
     * @return array{0: array{sections: array<int, array<string, mixed>>}|null, 1: ProductCategory|null}
     */
    public function resolveInherited(ProductCategory $category, AttributeContext $context, FormMode $formMode): array
    {
        $chain = [$category, ...$this->hierarchy->inheritedAncestors($category, $context)->reverse()->all()];

        return $this->firstConfigured($chain, $context, [$formMode->value, LayoutFormScope::All->value], $category);
    }

    /**
     * Walks $chain (NEAREST first) and returns the first level configured on
     * any of $scopes, in $scopes' own order of precedence, plus that level
     * when it is not $self.
     *
     * @param  list<ProductCategory>  $chain
     * @param  list<string>  $scopes
     * @return array{0: array{sections: array<int, array<string, mixed>>}|null, 1: ProductCategory|null}
     */
    private function firstConfigured(array $chain, AttributeContext $context, array $scopes, ProductCategory $self): array
    {
        // Step 1: one query for every candidate row of the whole chain (AC-008)
        $byCategory = $this->chainRows($chain, $context, $scopes);

        // Step 2: the nearest level carrying any candidate scope answers for the rest
        foreach ($chain as $level) {
            foreach ($scopes as $scope) {
                $layout = $byCategory[$level->id][$scope] ?? null;

                if ($layout !== null) {
                    return [$layout, $level->is($self) ? null : $level];
                }
            }
        }

        return [null, null];
    }

    /**
     * The scopes an authoring load falls back through, most specific first:
     * a per-mode scope still sees the shared layout beneath it, the shared
     * scope has nothing beneath it.
     *
     * @return list<string>
     */
    private static function scopeCandidates(LayoutFormScope $scope): array
    {
        return $scope === LayoutFormScope::All
            ? [LayoutFormScope::All->value]
            : [$scope->value, LayoutFormScope::All->value];
    }

    /**
     * The candidate rows of every level at once, as
     * category id => scope => blob — one query regardless of the chain's
     * depth, because the layout merger repeats this climb once per category
     * contributing to a record.
     *
     * @param  list<ProductCategory>  $chain
     * @param  list<string>  $scopes
     * @return array<int, array<string, array{sections: array<int, array<string, mixed>>}>>
     */
    private function chainRows(array $chain, AttributeContext $context, array $scopes): array
    {
        $rows = AttributeLayout::query()
            ->whereIn('product_category_id', array_map(static fn (ProductCategory $level): int => $level->id, $chain))
            ->where('context', $context->value)
            ->whereIn('form_mode', $scopes)
            ->get();

        $byCategory = [];

        foreach ($rows as $row) {
            $byCategory[$row->product_category_id][$row->form_mode->value] = $row->layout;
        }

        return $byCategory;
    }

    /**
     * Validates (allow-list + shape) and upserts $layout for (category,
     * context, scope). A null layout, or one with no sections, DELETES the
     * row instead and returns null — both the documented "back to flat"
     * reset of the shared scope and, on a per-mode scope, the "drop this
     * override and go back to the shared layout" action. Otherwise persists
     * a canonical, type-normalized copy and returns it (idempotent: two
     * identical PUTs leave exactly one row).
     *
     * @param  array<string, mixed>|null  $layout
     * @return array{sections: array<int, array<string, mixed>>}|null
     *
     * @throws ValidationException
     */
    public function upsert(ProductCategory $category, AttributeContext $context, LayoutFormScope $scope, ?array $layout): ?array
    {
        $this->validator->validate($category, $context, $layout);

        return DB::transaction(function () use ($category, $context, $scope, $layout): ?array {
            if ($layout === null || ($layout['sections'] ?? []) === []) {
                $this->find($category, $context, $scope)?->delete();

                return null;
            }

            $normalized = $this->normalize($layout);

            AttributeLayout::query()->updateOrCreate(
                [
                    'product_category_id' => $category->id,
                    'context' => $context->value,
                    'form_mode' => $scope->value,
                ],
                ['layout' => $normalized],
            );

            return $normalized;
        });
    }

    private function find(ProductCategory $category, AttributeContext $context, LayoutFormScope $scope): ?AttributeLayout
    {
        return AttributeLayout::query()
            ->where('product_category_id', $category->id)
            ->where('context', $context->value)
            ->where('form_mode', $scope->value)
            ->first();
    }

    /**
     * Rebuilds the blob field-by-field with explicit primitive casts,
     * dropping anything not in the contract shape — defense in depth
     * (security.md: never trust the client blob verbatim) and the source of
     * the "same normalized blob" idempotence (AC-002).
     *
     * @param  array<string, mixed>  $layout
     * @return array{sections: array<int, array<string, mixed>>}
     */
    private function normalize(array $layout): array
    {
        return [
            'sections' => array_values(array_map(
                $this->normalizeSection(...),
                $layout['sections'],
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    private function normalizeSection(array $section): array
    {
        return [
            'id' => (string) $section['id'],
            'title' => (string) $section['title'],
            'description' => isset($section['description']) ? (string) $section['description'] : null,
            'variant' => LayoutSectionVariant::from($section['variant'])->value,
            'collapsible' => (bool) $section['collapsible'],
            'default_collapsed' => (bool) $section['default_collapsed'],
            'columns' => (int) $section['columns'],
            'sort_order' => (int) $section['sort_order'],
            'rows' => array_values(array_map($this->normalizeRow(...), $section['rows'])),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'items' => array_values(array_map($this->normalizeItem(...), $row['items'])),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeItem(array $item): array
    {
        return [
            'attribute_code' => (string) $item['attribute_code'],
            'width' => LayoutItemWidth::from($item['width'])->value,
        ];
    }
}
