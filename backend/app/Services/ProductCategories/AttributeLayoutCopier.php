<?php

declare(strict_types=1);

namespace App\Services\ProductCategories;

use App\Enums\AttributeContext;
use App\Models\AttributeLayout;
use App\Models\ProductCategory;

/**
 * Copies a category's OWN attribute layouts (every context and scope) onto
 * another category — the row action "duplicate". The copy may be saved with
 * a different parent or attribute set than its source, so each blob is first
 * pruned to the target's effective attributes for that context (items gone,
 * then rows and sections left empty); a layout pruned to nothing is simply
 * not copied. What remains still goes through AttributeLayoutService::upsert,
 * the same validation and normalization as a layout authored by hand.
 * Inherited layouts are not copied: the target inherits them on its own.
 */
final class AttributeLayoutCopier
{
    public function __construct(
        private readonly AttributeLayoutService $layouts,
        private readonly CategoryHierarchy $hierarchy,
    ) {}

    public function copy(ProductCategory $source, ProductCategory $target): void
    {
        $rows = AttributeLayout::query()->where('product_category_id', $source->id)->get();
        $allowedCodesByContext = [];

        foreach ($rows as $row) {
            $context = $row->context;
            $allowedCodesByContext[$context->value] ??= $this->allowedCodes($target, $context);

            $layout = $this->prune($row->layout ?? [], $allowedCodesByContext[$context->value]);

            if ($layout['sections'] !== []) {
                $this->layouts->upsert($target, $context, $row->form_mode, $layout);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function allowedCodes(ProductCategory $category, AttributeContext $context): array
    {
        return $this->hierarchy->effectiveAttributes($category, $context)->pluck('code')->all();
    }

    /**
     * @param  array<string, mixed>  $layout
     * @param  array<int, string>  $allowedCodes
     * @return array{sections: array<int, array<string, mixed>>}
     */
    private function prune(array $layout, array $allowedCodes): array
    {
        $sections = [];

        foreach ($layout['sections'] ?? [] as $section) {
            $rows = [];

            foreach ($section['rows'] ?? [] as $row) {
                $items = array_values(array_filter(
                    $row['items'] ?? [],
                    static fn (array $item): bool => in_array($item['attribute_code'] ?? null, $allowedCodes, true),
                ));

                if ($items !== []) {
                    $rows[] = [...$row, 'items' => $items];
                }
            }

            if ($rows !== []) {
                $sections[] = [...$section, 'rows' => $rows];
            }
        }

        return ['sections' => $sections];
    }
}
