<?php

declare(strict_types=1);

namespace App\Services\ProductCategories;

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Enums\LayoutItemWidth;
use App\Enums\LayoutSectionVariant;
use App\Models\AttributeLayout;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Read/write for a category's configured layout, one row per (category,
 * context, form_mode) — spec 0062, D1/D3. The name (`resolveForProduct`)
 * reflects this class's primary caller (the Product form/detail, which
 * resolves ITS OWN category's layout); the SAME method resolves each
 * contributing category's layout for the Opportunity multi-category merge
 * (App\RequestManagement\OpportunityAttributeLayoutResolver) — this class is
 * otherwise context-agnostic.
 */
final class AttributeLayoutService
{
    /**
     * Cross-mode fallback precedence (spec 0062 revised): a single layout is
     * meant to drive every form mode, so when the requested mode has no row of
     * its own the first configured mode in this order is used instead. Only the
     * CONSUMPTION paths (product form/detail) resolve with fallback; authoring
     * (the configurator) and the Opportunity path stay exact via
     * resolveForProduct.
     */
    private const FALLBACK_ORDER = [FormMode::Create, FormMode::Edit, FormMode::View];

    public function __construct(
        private readonly AttributeLayoutValidator $validator,
    ) {}

    /**
     * The persisted, raw blob for the EXACT (category, context, form_mode) —
     * or null when that specific row is not configured. Used by the
     * configurator's authoring load and the Opportunity resolver, which both
     * need the raw per-mode row, never a cross-mode inheritance.
     *
     * @return array{sections: array<int, array<string, mixed>>}|null
     */
    public function resolveForProduct(ProductCategory $category, AttributeContext $context, FormMode $formMode): ?array
    {
        return $this->find($category, $context, $formMode)?->layout;
    }

    /**
     * The layout to RENDER for (category, context, form_mode): the exact mode
     * when configured, otherwise the first configured mode in FALLBACK_ORDER —
     * so one saved layout applies across create/edit/view. Null only when the
     * category has no layout in this context at all (flat fallback, AC-007).
     * All fallback candidates share the same context, so their attribute_code
     * references stay valid for the consuming form.
     *
     * @return array{sections: array<int, array<string, mixed>>}|null
     */
    public function resolveWithFallback(ProductCategory $category, AttributeContext $context, FormMode $formMode): ?array
    {
        // Step 1: one query for every mode row in this context, keyed by mode value
        $byMode = AttributeLayout::query()
            ->where('product_category_id', $category->id)
            ->where('context', $context->value)
            ->get()
            ->keyBy(fn (AttributeLayout $row): string => $row->form_mode->value);

        // Step 2: the requested mode wins when it carries a layout
        $exact = $byMode->get($formMode->value)?->layout;
        if ($exact !== null) {
            return $exact;
        }

        // Step 3: otherwise the first configured mode in canonical precedence
        foreach (self::FALLBACK_ORDER as $mode) {
            $layout = $byMode->get($mode->value)?->layout;
            if ($layout !== null) {
                return $layout;
            }
        }

        return null;
    }

    /**
     * Validates (allow-list + shape) and upserts $layout for (category,
     * context, form_mode). A null layout, or one with no sections, DELETES
     * the row instead — the documented "back to flat" reset — and returns
     * null. Otherwise persists a canonical, type-normalized copy and returns
     * it (idempotent: two identical PUTs leave exactly one row).
     *
     * @param  array<string, mixed>|null  $layout
     * @return array{sections: array<int, array<string, mixed>>}|null
     *
     * @throws ValidationException
     */
    public function upsert(ProductCategory $category, AttributeContext $context, FormMode $formMode, ?array $layout): ?array
    {
        $this->validator->validate($category, $context, $layout);

        return DB::transaction(function () use ($category, $context, $formMode, $layout): ?array {
            if ($layout === null || ($layout['sections'] ?? []) === []) {
                $this->find($category, $context, $formMode)?->delete();

                return null;
            }

            $normalized = $this->normalize($layout);

            AttributeLayout::query()->updateOrCreate(
                [
                    'product_category_id' => $category->id,
                    'context' => $context->value,
                    'form_mode' => $formMode->value,
                ],
                ['layout' => $normalized],
            );

            return $normalized;
        });
    }

    private function find(ProductCategory $category, AttributeContext $context, FormMode $formMode): ?AttributeLayout
    {
        return AttributeLayout::query()
            ->where('product_category_id', $category->id)
            ->where('context', $context->value)
            ->where('form_mode', $formMode->value)
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
