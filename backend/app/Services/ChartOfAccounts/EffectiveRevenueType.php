<?php

declare(strict_types=1);

namespace App\Services\ChartOfAccounts;

use App\Models\Product;
use App\Models\ProductCategory;

/**
 * Read-side "effective revenue sub-account" of a product or a category
 * (spec 0350, D-3), with where it comes from: the node's own value, or the
 * first category that has one walking toward the root. Detail endpoints only
 * (one category-tree query per call).
 *
 * @phpstan-type Effective array{revenue_type: array<string, mixed>, inherited: bool, source_category: array{id: int, name: string}|null}
 */
final class EffectiveRevenueType
{
    public function __construct(private readonly RevenueTypeResolver $resolver) {}

    /**
     * @return Effective|null
     */
    public function forProduct(Product $product): ?array
    {
        if ($product->revenue_type_id !== null) {
            return $this->own($product->revenue_type_id);
        }

        return $this->fromCategory($product->category_id);
    }

    /**
     * @return Effective|null
     */
    public function forCategory(ProductCategory $category): ?array
    {
        if ($category->revenue_type_id !== null) {
            return $this->own($category->revenue_type_id);
        }

        return $this->fromCategory($category->parent_id);
    }

    /**
     * @return Effective|null
     */
    private function own(int $revenueTypeId): ?array
    {
        $ref = RevenueTypeRef::find($revenueTypeId);

        return $ref === null ? null : ['revenue_type' => $ref, 'inherited' => false, 'source_category' => null];
    }

    /**
     * @return Effective|null
     */
    private function fromCategory(?int $categoryId): ?array
    {
        if ($categoryId === null) {
            return null;
        }

        $source = $this->resolver->categorySource($categoryId, $this->resolver->categoryNodes());
        $ref = RevenueTypeRef::find($source['revenue_type_id']);

        if ($ref === null) {
            return null;
        }

        return [
            'revenue_type' => $ref,
            'inherited' => true,
            'source_category' => ['id' => $source['id'], 'name' => (string) ProductCategory::query()->whereKey($source['id'])->value('name')],
        ];
    }
}
