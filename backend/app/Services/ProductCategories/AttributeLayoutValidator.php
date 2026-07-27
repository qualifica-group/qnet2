<?php

declare(strict_types=1);

namespace App\Services\ProductCategories;

use App\Enums\AttributeContext;
use App\Enums\LayoutItemWidth;
use App\Enums\LayoutSectionVariant;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validates a layout blob (spec 0062, layout-contract) before it is
 * persisted: PUT /api/product-categories/{productCategory}/attribute-layouts
 * only gets a shallow `array` check at the FormRequest layer (mirrors
 * App\RequestManagement\AttributeValueValidator's split for `attribute_values`)
 * — the deep shape rules (enums, limits, structure) AND the ALLOW-LIST check
 * (every `attribute_code` must be among $category's EFFECTIVE attributes for
 * $context, CategoryHierarchy::effectiveAttributes()) both happen HERE, at
 * the service layer, because the allow-list is per-category/per-context data
 * a static FormRequest rules() array cannot see.
 *
 * Two failure classes, two error shapes:
 *  - shape/enum/limit violations -> Laravel's own dotted paths
 *    (`layout.sections.0.variant`, ...);
 *  - unknown `attribute_code` / duplicate `attribute_code` -> both keyed
 *    `attribute_layout` (data_contract: "422 code sconosciuto -> attribute_layout").
 */
final class AttributeLayoutValidator
{
    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * No-op when $layout is null or carries no sections — that state means
     * "delete the row" (AttributeLayoutService::upsert), never something to
     * validate against the allow-list.
     *
     * @param  array<string, mixed>|null  $layout
     *
     * @throws ValidationException
     */
    public function validate(ProductCategory $category, AttributeContext $context, ?array $layout): void
    {
        if ($layout === null || ($layout['sections'] ?? []) === []) {
            return;
        }

        // Step 1: shape (enums, limits, required structure)
        $this->assertShape($layout);

        // Step 2: allow-list + no-duplicate-code, against the category's
        // EFFECTIVE attributes for this context
        $this->assertCodes($category, $context, $layout);
    }

    /**
     * @param  array<string, mixed>  $layout
     *
     * @throws ValidationException
     */
    private function assertShape(array $layout): void
    {
        ValidatorFacade::make(['layout' => $layout], $this->shapeRules())->validate();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function shapeRules(): array
    {
        return [
            'layout.sections' => ['array'],
            'layout.sections.*.id' => ['required', 'string', 'max:191'],
            'layout.sections.*.title' => ['required', 'string', 'max:191'],
            'layout.sections.*.description' => ['nullable', 'string', 'max:500'],
            'layout.sections.*.variant' => ['required', Rule::enum(LayoutSectionVariant::class)],
            'layout.sections.*.collapsible' => ['required', 'boolean'],
            'layout.sections.*.default_collapsed' => ['required', 'boolean'],
            'layout.sections.*.columns' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'layout.sections.*.sort_order' => ['required', 'integer'],
            'layout.sections.*.rows' => ['required', 'array'],
            'layout.sections.*.rows.*.id' => ['required', 'string', 'max:191'],
            'layout.sections.*.rows.*.items' => ['required', 'array', 'min:1'],
            'layout.sections.*.rows.*.items.*.attribute_code' => ['required', 'string', 'max:191'],
            'layout.sections.*.rows.*.items.*.width' => ['required', Rule::enum(LayoutItemWidth::class)],
        ];
    }

    /**
     * @param  array<string, mixed>  $layout
     *
     * @throws ValidationException
     */
    private function assertCodes(ProductCategory $category, AttributeContext $context, array $layout): void
    {
        $allowedCodes = $this->hierarchy->effectiveAttributes($category, $context)->pluck('code')->all();
        $seen = [];

        foreach ($this->eachItem($layout) as $code) {
            if (! in_array($code, $allowedCodes, true)) {
                throw ValidationException::withMessages([
                    'attribute_layout' => ["The \"{$code}\" attribute is not part of this category's effective attributes for the {$context->value} context."],
                ]);
            }

            if (isset($seen[$code])) {
                throw ValidationException::withMessages([
                    'attribute_layout' => ["The \"{$code}\" attribute is placed more than once in the layout."],
                ]);
            }

            $seen[$code] = true;
        }
    }

    /**
     * @param  array<string, mixed>  $layout
     * @return iterable<int, string>
     */
    private function eachItem(array $layout): iterable
    {
        foreach (($layout['sections'] ?? []) as $section) {
            foreach (($section['rows'] ?? []) as $row) {
                foreach (($row['items'] ?? []) as $item) {
                    if (is_array($item) && is_string($item['attribute_code'] ?? null)) {
                        yield $item['attribute_code'];
                    }
                }
            }
        }
    }
}
