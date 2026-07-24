<?php

declare(strict_types=1);

namespace App\RequestManagement;

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The Opportunity-level attribute layout (spec 0062,
 * layout-contract/opportunity-layout-resolution): unlike a Product (one
 * category), an Opportunity's form merges several categories'
 * (context=opportunity, $formMode) layouts, server-side, WITHOUT touching
 * the value-pipeline (App\RequestManagement\ApplicableAttributesResolver
 * stays the single authority on which codes are applicable/required — this
 * class only decides how the ALREADY-merged set is grouped for display).
 *
 * Algorithm:
 *  1. distinct contributing categories, in product-line order (mirrors
 *     ApplicableAttributesResolver::distinctCategories — same dedup, so the
 *     two resolvers agree on "which categories contribute" and in what order);
 *  2. per category, load its own persisted layout for (opportunity, $formMode);
 *  3. concatenate sections in category order, dropping any item whose code
 *     was already placed by an EARLIER category (first-wins) or that fell
 *     outside the merged applicable set — pruning empty rows/sections along
 *     the way;
 *  4. any applicable code no category ever placed lands in a synthetic
 *     trailing "Altre informazioni" section (one item per row, `full` width
 *     — the same flat shape used when there is no layout at all).
 *
 * No contributing category has ANY layout -> null (flat fallback, AC-007).
 */
final class OpportunityAttributeLayoutResolver
{
    private const string UNPLACED_SECTION_TITLE = 'Altre informazioni';

    public function __construct(
        private readonly ApplicableAttributesResolver $applicableAttributesResolver,
        private readonly AttributeLayoutService $layoutService,
    ) {}

    /**
     * @return array{sections: array<int, array<string, mixed>>}|null
     */
    public function resolve(Opportunity $opportunity, FormMode $formMode): ?array
    {
        // Step 1: the merged applicable set (SAME resolver the value-pipeline
        // uses) — both the placement filter and the "unplaced" leftover are
        // scoped to exactly these codes, never a raw category attribute list.
        $applicableCodes = $this->applicableAttributesResolver->resolve($opportunity)
            ->pluck('code')
            ->all();

        if ($applicableCodes === []) {
            return null;
        }

        $categories = $this->distinctCategories($opportunity);
        $placedCodes = [];
        $sections = [];
        $anyLayoutConfigured = false;

        // Step 2: concatenate each contributing category's own layout, in
        // order, deduping first-wins as we go.
        foreach ($categories as $category) {
            $layout = $this->layoutService->resolveForProduct($category, AttributeContext::Opportunity, $formMode);

            if ($layout !== null && ($layout['sections'] ?? []) !== []) {
                $anyLayoutConfigured = true;
            }

            foreach ($this->placeableSections($layout, $applicableCodes, $placedCodes) as $section) {
                $sections[] = $section;
            }
        }

        // No contributing category configured ANY layout -> flat fallback
        // (AC-007), regardless of the merged applicable set.
        if (! $anyLayoutConfigured) {
            return null;
        }

        // Step 3: leftover applicable codes, in the merged set's own order —
        // never lost, always surfaced.
        $leftover = array_values(array_diff($applicableCodes, array_keys($placedCodes)));

        if ($leftover !== []) {
            $sections[] = $this->unplacedSection($leftover, count($sections));
        }

        return ['sections' => $this->renumbered($sections)];
    }

    /**
     * Distinct product categories across $opportunity's product lines, in
     * FIRST-OCCURRENCE (product-line) order — mirrors
     * ApplicableAttributesResolver::distinctCategories exactly, so both
     * resolvers agree on category order without either exposing its private
     * method to the other.
     *
     * @return Collection<int, ProductCategory>
     */
    private function distinctCategories(Opportunity $opportunity): Collection
    {
        $opportunity->loadMissing('productLines.productCategory');

        return $opportunity->productLines
            ->pluck('productCategory')
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * $category's own sections, pruned to items whose code is BOTH
     * applicable and not yet placed by an earlier category — mutates
     * $placedCodes (by reference) as it goes, so the NEXT category (and the
     * final leftover computation) sees what THIS one consumed.
     *
     * @param  array{sections: array<int, array<string, mixed>>}|null  $layout
     * @param  array<int, string>  $applicableCodes
     * @param  array<string, bool>  $placedCodes
     * @return array<int, array<string, mixed>>
     */
    private function placeableSections(?array $layout, array $applicableCodes, array &$placedCodes): array
    {
        $sections = [];

        foreach (($layout['sections'] ?? []) as $section) {
            $rows = $this->placeableRows($section['rows'] ?? [], $applicableCodes, $placedCodes);

            if ($rows !== []) {
                $sections[] = [...$section, 'rows' => $rows];
            }
        }

        return $sections;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $applicableCodes
     * @param  array<string, bool>  $placedCodes
     * @return array<int, array<string, mixed>>
     */
    private function placeableRows(array $rows, array $applicableCodes, array &$placedCodes): array
    {
        $result = [];

        foreach ($rows as $row) {
            $items = array_values(array_filter(
                $row['items'] ?? [],
                function (array $item) use ($applicableCodes, &$placedCodes): bool {
                    $code = $item['attribute_code'];

                    if (isset($placedCodes[$code]) || ! in_array($code, $applicableCodes, true)) {
                        return false;
                    }

                    $placedCodes[$code] = true;

                    return true;
                },
            ));

            if ($items !== []) {
                $result[] = [...$row, 'items' => $items];
            }
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<string, mixed>
     */
    private function unplacedSection(array $codes, int $sortOrder): array
    {
        return [
            'id' => (string) Str::uuid(),
            'title' => self::UNPLACED_SECTION_TITLE,
            'description' => null,
            'variant' => 'default',
            'collapsible' => true,
            'default_collapsed' => true,
            'is_advanced' => true,
            'columns' => 1,
            'sort_order' => $sortOrder,
            'rows' => array_map(
                static fn (string $code): array => [
                    'id' => (string) Str::uuid(),
                    'items' => [['attribute_code' => $code, 'width' => 'full']],
                ],
                $codes,
            ),
        ];
    }

    /**
     * Final `sort_order` reassigned to the sections' own array position —
     * each contributing category's own numbering is meaningless once
     * concatenated across categories (they were never comparable to begin
     * with), so the merged order (category order, then each category's own
     * section order) becomes the new canonical sort_order.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function renumbered(array $sections): array
    {
        return array_values(array_map(
            static fn (array $section, int $index): array => [...$section, 'sort_order' => $index],
            $sections,
            array_keys($sections),
        ));
    }
}
