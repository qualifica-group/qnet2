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
 * and each contributing category of the Opportunity merge
 * (App\RequestManagement\OpportunityAttributeLayoutResolver) — resolves a
 * concrete FormMode through `resolveWithFallback`.
 */
final class AttributeLayoutService
{
    public function __construct(
        private readonly AttributeLayoutValidator $validator,
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
     * The layout to RENDER for (category, context, form_mode): the mode's own
     * override when configured, otherwise the shared `all` layout. Null only
     * when the category has neither in this context (flat fallback, AC-007).
     * Both candidates share the same context, so their attribute_code
     * references stay valid for the consuming form.
     *
     * @return array{sections: array<int, array<string, mixed>>}|null
     */
    public function resolveWithFallback(ProductCategory $category, AttributeContext $context, FormMode $formMode): ?array
    {
        // Step 1: one query for both candidate rows, keyed by their scope value
        $byScope = AttributeLayout::query()
            ->where('product_category_id', $category->id)
            ->where('context', $context->value)
            ->whereIn('form_mode', [$formMode->value, LayoutFormScope::All->value])
            ->get()
            ->keyBy(fn (AttributeLayout $row): string => $row->form_mode->value);

        // Step 2: the per-mode override wins, the shared layout is the fallback
        return $byScope->get($formMode->value)?->layout
            ?? $byScope->get(LayoutFormScope::All->value)?->layout;
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
