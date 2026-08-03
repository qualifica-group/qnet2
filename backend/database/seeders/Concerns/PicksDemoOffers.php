<?php

namespace Database\Seeders\Concerns;

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
 * Everything is loaded in one batch (no query per opportunity): the effective
 * business functions come from a single CategoryHierarchy call, the products
 * from a single projection.
 */
trait PicksDemoOffers
{
    private const int MAX_PRODUCT_LINES = 2;

    private const int MAX_PRODUCTS_PER_LINE = 2;

    /**
     * @var list<array{business_function_id: int, product_category_id: int, product_ids: list<int>}>
     */
    private array $offers = [];

    protected function loadOffers(CategoryHierarchy $hierarchy): void
    {
        $selectableIds = $this->selectableCategoryIds();
        $productIdsByCategory = $this->productIdsByCategory($hierarchy, $selectableIds);
        $offers = [];

        foreach ($hierarchy->effectiveBusinessFunctionSummaries() as $categoryId => $summary) {
            $productIds = $productIdsByCategory[$categoryId] ?? [];

            if ($summary === null || $productIds === []) {
                continue;
            }

            $offers[] = [
                'business_function_id' => $summary['id'],
                'product_category_id' => $categoryId,
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
     * opportunities cover the whole catalogue and the lines of a single one
     * never repeat a category, plus one to MAX_PRODUCTS_PER_LINE products of
     * each line's own category subtree.
     *
     * @return array{product_lines: list<array{business_function_id: int, product_category_id: int}>, products_of_interest: list<int>}
     */
    protected function pickOffer(Generator $faker, int $index): array
    {
        $lineCount = min($faker->numberBetween(1, self::MAX_PRODUCT_LINES), count($this->offers));
        $productLines = [];
        $productIds = [];

        for ($slot = 0; $slot < $lineCount; $slot++) {
            $offer = $this->offers[($index + $slot) % count($this->offers)];

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
     * AND of every descendant — so a line pointing at a branch root still draws
     * a real product, the one filed under one of its leaves, without the
     * coverage rule pushing a container onto the deal.
     *
     * @param  array<int, true>  $selectableIds
     * @return array<int, list<int>>
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
            $ids = $directIds[$categoryId] ?? [];

            foreach ($hierarchy->descendantIds($categoryId) as $descendantId) {
                $ids = [...$ids, ...($directIds[$descendantId] ?? [])];
            }

            if ($ids !== []) {
                $byCategory[$categoryId] = array_values(array_unique($ids));
            }
        }

        return $byCategory;
    }
}
