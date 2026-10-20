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
 * then rows and sections left empty; a section condition whose controlling
 * attribute did not survive is cleared, spec 0288); a layout pruned to
 * nothing is simply not copied. What remains still goes through AttributeLayoutService::upsert,
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

        return ['sections' => $this->clearOrphanConditions($sections)];
    }

    /**
     * A condition pointing at a pruned (or pruned-away section's) attribute
     * would fail the validation of the copy; the section stays, unconditional.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function clearOrphanConditions(array $sections): array
    {
        $placed = [];

        foreach ($sections as $section) {
            foreach ($section['rows'] as $row) {
                foreach ($row['items'] as $item) {
                    $placed[$item['attribute_code']] = true;
                }
            }
        }

        return array_map(static function (array $section) use ($placed): array {
            $controlCode = $section['visible_when']['attribute_code'] ?? null;

            return $controlCode !== null && ! isset($placed[$controlCode])
                ? [...$section, 'visible_when' => null]
                : $section;
        }, $sections);
    }
}
