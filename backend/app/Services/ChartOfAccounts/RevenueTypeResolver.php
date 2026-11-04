<?php

declare(strict_types=1);

namespace App\Services\ChartOfAccounts;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RevenueType;

/**
 * Single source of the revenue sub-account PROPOSED for a line (spec 0350, D-6):
 * a line of an institution invoice takes the institution-commission default;
 * any other line takes its product's sub-account, else the effective one of the
 * product's category (own or first ancestor that has it); else null ("to be
 * classified"). Batch by design: one call costs at most three queries (default,
 * products, category tree) whatever the number of lines.
 */
final class RevenueTypeResolver
{
    /** Defensive cap on the category walk (the write side prevents cycles). */
    private const int MAX_DEPTH = 100;

    /**
     * @param  array<int|string, array{product_id: int|null, institution: bool}>  $items  keyed by the caller
     * @return array<int|string, int|null> same keys, proposed revenue_type_id
     */
    public function resolve(array $items): array
    {
        $defaultId = in_array(true, array_column($items, 'institution'), true) ? $this->institutionDefaultId() : null;
        $byProduct = $this->forProducts(array_values(array_filter(array_column(array_filter($items, static fn (array $item): bool => ! $item['institution']), 'product_id'))));
        $proposals = [];

        foreach ($items as $key => $item) {
            $proposals[$key] = $item['institution'] ? $defaultId : ($byProduct[$item['product_id']] ?? null);
        }

        return $proposals;
    }

    /**
     * Fills `revenue_type_id` on the payload lines that do not carry the key;
     * a present key (id or null) is the user's choice and is never touched
     * (D-7).
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public function fillMissing(array $lines, bool $institution): array
    {
        $missing = [];

        foreach ($lines as $index => $line) {
            if (! array_key_exists('revenue_type_id', $line)) {
                $missing[$index] = ['product_id' => isset($line['product_id']) ? (int) $line['product_id'] : null, 'institution' => $institution];
            }
        }

        foreach ($this->resolve($missing) as $index => $revenueTypeId) {
            $lines[$index]['revenue_type_id'] = $revenueTypeId;
        }

        return $lines;
    }

    /**
     * Effective sub-account id of each product: its own, else its category's.
     *
     * @param  array<int, int>  $productIds
     * @return array<int, int|null> product id => revenue_type_id
     */
    public function forProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $products = Product::query()->whereIn('id', array_unique($productIds))->get(['id', 'category_id', 'revenue_type_id']);
        $needsCategory = $products->contains(static fn (Product $product): bool => $product->revenue_type_id === null && $product->category_id !== null);
        $categories = $needsCategory ? $this->categoryNodes() : [];
        $resolved = [];

        foreach ($products as $product) {
            $resolved[$product->id] = $product->revenue_type_id
                ?? $this->categorySource($product->category_id, $categories)['revenue_type_id'] ?? null;
        }

        return $resolved;
    }

    /**
     * Ids of the products whose effective sub-account is (or would become, if
     * the category's one is cleared) decided by $categoryId: the products of
     * the category and of its descendants with no own sub-account and no closer
     * category that has one.
     *
     * @return array<int, int>
     */
    public function productIdsInheritingFrom(int $categoryId): array
    {
        $categories = $this->categoryNodes();
        $candidateIds = array_values(array_filter(
            array_keys($categories),
            fn (int $id): bool => $this->reaches($id, $categoryId, $categories),
        ));

        return Product::query()->whereIn('category_id', $candidateIds)->whereNull('revenue_type_id')->pluck('id')->all();
    }

    /**
     * Whether walking up from $nodeId reaches $targetId without meeting a
     * category with its own sub-account first ($targetId itself is exempt).
     *
     * @param  array<int, array{parent_id: int|null, revenue_type_id: int|null}>  $categories
     */
    private function reaches(int $nodeId, int $targetId, array $categories): bool
    {
        $depth = 0;

        while ($nodeId !== $targetId) {
            $node = $categories[$nodeId] ?? null;

            if ($node === null || $node['revenue_type_id'] !== null || $node['parent_id'] === null || $depth++ >= self::MAX_DEPTH) {
                return false;
            }

            $nodeId = $node['parent_id'];
        }

        return true;
    }

    /**
     * The category that supplies the effective sub-account of a node walking
     * toward the root, with that sub-account.
     *
     * @param  array<int, array{parent_id: int|null, revenue_type_id: int|null}>  $categories
     * @return array{id: int|null, revenue_type_id: int|null}
     */
    public function categorySource(?int $categoryId, array $categories): array
    {
        $depth = 0;

        while ($categoryId !== null && isset($categories[$categoryId]) && $depth < self::MAX_DEPTH) {
            if ($categories[$categoryId]['revenue_type_id'] !== null) {
                return ['id' => $categoryId, 'revenue_type_id' => $categories[$categoryId]['revenue_type_id']];
            }

            $categoryId = $categories[$categoryId]['parent_id'];
            $depth++;
        }

        return ['id' => null, 'revenue_type_id' => null];
    }

    /**
     * Every category as id => {parent_id, revenue_type_id}, in ONE query.
     *
     * @return array<int, array{parent_id: int|null, revenue_type_id: int|null}>
     */
    public function categoryNodes(): array
    {
        return ProductCategory::query()->get(['id', 'parent_id', 'revenue_type_id'])
            ->mapWithKeys(static fn (ProductCategory $category): array => [
                $category->id => ['parent_id' => $category->parent_id, 'revenue_type_id' => $category->revenue_type_id],
            ])->all();
    }

    public function institutionDefaultId(): ?int
    {
        return RevenueType::query()->where('is_institution_commission_default', true)->value('id');
    }
}
