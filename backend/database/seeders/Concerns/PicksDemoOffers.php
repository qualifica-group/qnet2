<?php

namespace Database\Seeders\Concerns;

use App\Enums\CategoryManagementMode;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Faker\Generator;

/**
 * Picks the two MANDATORY collections of the opportunity form — `product_lines`
 * (a {business_function_id, product_category_id} pair) and
 * `products_of_interest` — as one coherent draw, for the demo seeders that
 * create opportunities.
 *
 * An "offer" is a category that can carry BOTH: it resolves an EFFECTIVE
 * business function (its own or an ancestor's — without one the pair is
 * invalid) AND it has at least one product in itself or in a descendant, so
 * the products drawn alongside a line never force
 * OpportunityProductInterestWriter to add a category the seeder did not choose.
 *
 * On top of that an offer is always SELECTABLE (spec 0074, user directive
 * 2026-08-03): a category flagged `is_selectable = false` is a container, and
 * the App\Rules\SelectableProductCategory rule rejects it on every write path
 * the form uses. Seeding a line on one would produce a deal the edit form
 * itself refuses to resubmit. The filter applies TWICE — to the category the
 * line points at, and to the category the drawn products are filed on, because
 * OpportunityProductLineCoverage adds a line for each product's OWN category.
 *
 * Each offer also carries its branch ROOT and that root's management mode
 * (spec 0077), because a card's lines are not free to combine: they must all
 * resolve to the same root (INV-1) and share one business function (INV-2),
 * and a `single` root carries exactly one line (INV-3). The seeders write
 * through OpportunityService — no FormRequest, hence no ProductLineSetValidator
 * — so a draw that ignored the invariants would produce demo deals the edit
 * form itself refuses to resubmit.
 *
 * Everything is loaded in one batch (no query per opportunity): the effective
 * business functions and the branch roots come from two CategoryHierarchy
 * calls, the products from a single projection.
 */
trait PicksDemoOffers
{
    private const int MAX_PRODUCT_LINES = 2;

    private const int MAX_PRODUCTS_PER_LINE = 2;

    /**
     * @var list<array{business_function_id: int, product_category_id: int, root_category_id: int, management_mode: CategoryManagementMode, product_ids: list<int>}>
     */
    private array $offers = [];

    protected function loadOffers(CategoryHierarchy $hierarchy): void
    {
        $selectableIds = $this->selectableCategoryIds();
        $productIdsByCategory = $this->productIdsByCategory($hierarchy, $selectableIds);
        $summaries = $hierarchy->effectiveBusinessFunctionSummaries();
        $roots = $hierarchy->rootManagementModesFor(array_keys($summaries));
        $offers = [];

        foreach ($summaries as $categoryId => $summary) {
            $root = $roots[$categoryId] ?? null;
            // On a `single` root the coverage rule REJECTS a product filed
            // outside the one covered category (spec 0077 D-6/AC-020) instead
            // of widening the card, so such an offer may only draw the
            // products of its OWN category — and is no offer at all without.
            $productIds = $root !== null && $root['management_mode'] === CategoryManagementMode::Single
                ? ($productIdsByCategory[$categoryId]['own'] ?? [])
                : ($productIdsByCategory[$categoryId]['subtree'] ?? []);

            if ($summary === null || $productIds === [] || $root === null) {
                continue;
            }

            $offers[] = [
                'business_function_id' => $summary['id'],
                'product_category_id' => $categoryId,
                'root_category_id' => $root['root_id'],
                'management_mode' => $root['management_mode'],
                'product_ids' => $productIds,
            ];
        }

        $this->offers = $offers;
    }

    protected function hasOffers(): bool
    {
        return $this->offers !== [];
    }

    /**
     * One to MAX_PRODUCT_LINES lines, rotated by $index so consecutive
     * opportunities cover the whole catalogue, plus one to
     * MAX_PRODUCTS_PER_LINE products of each line's own category subtree.
     *
     * The FIRST line decides the card (spec 0077): a `single` root ends the
     * draw there, a `multiple` one may take further lines, but only among the
     * offers that share its root AND its business function — so the seeded
     * collection satisfies INV-1..INV-4 exactly as the form would demand.
     *
     * @return array{product_lines: list<array{business_function_id: int, product_category_id: int}>, products_of_interest: list<int>}
     */
    protected function pickOffer(Generator $faker, int $index): array
    {
        $primary = $this->offers[$index % count($this->offers)];
        $lines = [$primary, ...$this->companionLines($faker, $index, $primary)];
        $productLines = [];
        $productIds = [];

        foreach ($lines as $offer) {
            $productLines[] = [
                'business_function_id' => $offer['business_function_id'],
                'product_category_id' => $offer['product_category_id'],
            ];

            $productCount = min($faker->numberBetween(1, self::MAX_PRODUCTS_PER_LINE), count($offer['product_ids']));
            $productIds = [...$productIds, ...$faker->randomElements($offer['product_ids'], $productCount)];
        }

        return [
            'product_lines' => $productLines,
            'products_of_interest' => array_values(array_unique($productIds)),
        ];
    }

    /**
     * The lines that may join $primary on the same card, rotated by $index so
     * they never repeat its category: none at all on a `single` root (INV-3),
     * otherwise up to MAX_PRODUCT_LINES in total, drawn among the offers of
     * the SAME root (INV-1) under the SAME business function (INV-2).
     *
     * @param  array{business_function_id: int, product_category_id: int, root_category_id: int, management_mode: CategoryManagementMode, product_ids: list<int>}  $primary
     * @return list<array{business_function_id: int, product_category_id: int, root_category_id: int, management_mode: CategoryManagementMode, product_ids: list<int>}>
     */
    private function companionLines(Generator $faker, int $index, array $primary): array
    {
        if ($primary['management_mode'] === CategoryManagementMode::Single) {
            return [];
        }

        $candidates = array_values(array_filter(
            $this->offers,
            static fn (array $offer): bool => $offer['root_category_id'] === $primary['root_category_id']
                && $offer['business_function_id'] === $primary['business_function_id']
                && $offer['product_category_id'] !== $primary['product_category_id'],
        ));

        if ($candidates === []) {
            return [];
        }

        $extraCount = min($faker->numberBetween(1, self::MAX_PRODUCT_LINES), count($candidates) + 1) - 1;
        $companions = [];

        for ($slot = 0; $slot < $extraCount; $slot++) {
            $companions[] = $candidates[($index + $slot) % count($candidates)];
        }

        return $companions;
    }

    /**
     * The classification TARGETS, in id order: an unselectable category is a
     * container the form refuses, so it is neither offered as a line nor used
     * to draw a product.
     *
     * @return array<int, true>
     */
    private function selectableCategoryIds(): array
    {
        return array_fill_keys(
            ProductCategory::query()->where('is_selectable', true)->orderBy('id')->pluck('id')->all(),
            true,
        );
    }

    /**
     * selectable category id => the SELECTABLE-filed products of that category
     * alone (`own`) and those of the category AND every descendant
     * (`subtree`) — so a line pointing at a branch root still draws a real
     * product, the one filed under one of its leaves, without the coverage
     * rule pushing a container onto the deal, while a `single` card can stay
     * strictly inside its own category.
     *
     * @param  array<int, true>  $selectableIds
     * @return array<int, array{own: list<int>, subtree: list<int>}>
     */
    private function productIdsByCategory(CategoryHierarchy $hierarchy, array $selectableIds): array
    {
        $directIds = Product::query()
            ->whereIn('category_id', array_keys($selectableIds))
            ->orderBy('id')
            ->get(['id', 'category_id'])
            ->groupBy('category_id')
            ->map(static fn ($products): array => $products->pluck('id')->all())
            ->all();

        $byCategory = [];

        foreach (array_keys($selectableIds) as $categoryId) {
            $own = $directIds[$categoryId] ?? [];
            $subtree = $own;

            foreach ($hierarchy->descendantIds($categoryId) as $descendantId) {
                $subtree = [...$subtree, ...($directIds[$descendantId] ?? [])];
            }

            if ($subtree !== []) {
                $byCategory[$categoryId] = [
                    'own' => array_values(array_unique($own)),
                    'subtree' => array_values(array_unique($subtree)),
                ];
            }
        }

        return $byCategory;
    }
}
