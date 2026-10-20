<?php

declare(strict_types=1);

namespace App\Services\ProductCategories;

use App\Enums\AttributeContext;
use App\Enums\LayoutItemWidth;
use App\Enums\LayoutSectionVariant;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;
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
 *  - unknown `attribute_code` / duplicate `attribute_code` / an invalid
 *    section condition (`visible_when`, spec 0288 D-2) -> all keyed
 *    `attribute_layout` (data_contract: "422 code sconosciuto -> attribute_layout").
 */
final class AttributeLayoutValidator
{
    /** The only attribute type a section condition can read (spec 0288 D-1). */
    private const string CONDITION_ATTRIBUTE_TYPE = 'boolean';

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

        $effective = $this->hierarchy->effectiveAttributes($category, $context);

        // Step 2: allow-list + no-duplicate-code, against the category's
        // EFFECTIVE attributes for this context
        $this->assertCodes($context, $layout, $effective);

        // Step 3: section conditions (spec 0288 D-2), which read the types of
        // the same effective attributes
        $this->assertConditions($layout, $effective);
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
            'layout.sections.*.visible_when' => ['nullable', 'array'],
            'layout.sections.*.visible_when.attribute_code' => ['required_with:layout.sections.*.visible_when', 'string', 'max:191'],
            'layout.sections.*.rows' => ['required', 'array'],
            'layout.sections.*.rows.*.id' => ['required', 'string', 'max:191'],
            'layout.sections.*.rows.*.items' => ['required', 'array', 'min:1'],
            'layout.sections.*.rows.*.items.*.attribute_code' => ['required', 'string', 'max:191'],
            'layout.sections.*.rows.*.items.*.width' => ['required', Rule::enum(LayoutItemWidth::class)],
        ];
    }

    /**
     * @param  array<string, mixed>  $layout
     * @param  Collection<int, array<string, mixed>>  $effective
     *
     * @throws ValidationException
     */
    private function assertCodes(AttributeContext $context, array $layout, Collection $effective): void
    {
        $allowedCodes = $effective->pluck('code')->all();
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
     * Each controlling attribute must be placed in this layout, be a boolean,
     * sit outside the section it controls and inside an unconditional one
     * (no chains): a condition chain could never be evaluated without a
     * cycle guard, and the editor only offers what passes these rules.
     *
     * @param  array<string, mixed>  $layout
     * @param  Collection<int, array<string, mixed>>  $effective
     *
     * @throws ValidationException
     */
    private function assertConditions(array $layout, Collection $effective): void
    {
        $sectionByCode = [];

        foreach ($layout['sections'] as $index => $section) {
            foreach ($this->eachItem(['sections' => [$section]]) as $code) {
                $sectionByCode[$code] = $index;
            }
        }

        $typeByCode = $effective->pluck('type', 'code');

        foreach ($layout['sections'] as $index => $section) {
            $controlCode = $section['visible_when']['attribute_code'] ?? null;

            if ($controlCode === null) {
                continue;
            }

            $message = match (true) {
                ! isset($sectionByCode[$controlCode]) => "The \"{$controlCode}\" attribute controls a section but is not placed in the layout.",
                $typeByCode->get($controlCode) !== self::CONDITION_ATTRIBUTE_TYPE => "The \"{$controlCode}\" attribute controls a section but is not a boolean attribute.",
                $sectionByCode[$controlCode] === $index => "The \"{$controlCode}\" attribute cannot control the section it is placed in.",
                ($layout['sections'][$sectionByCode[$controlCode]]['visible_when'] ?? null) !== null => "The \"{$controlCode}\" attribute sits in a conditional section and cannot control another section.",
                default => null,
            };

            if ($message !== null) {
                throw ValidationException::withMessages(['attribute_layout' => [$message]]);
            }
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
