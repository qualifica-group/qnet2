<?php

declare(strict_types=1);

namespace App\Products;

use App\Enums\AttributeContext;
use App\Models\Product;
use App\RequestManagement\ApplicableAttribute;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Support\Collection;

/**
 * The product-level "applicable attributes" set (spec 0061): the EFFECTIVE
 * (own + inherited) Product-context attributes of the product's OWN category
 * (App\Services\ProductCategories\CategoryHierarchy::effectiveAttributes()).
 * Mirrors App\RequestManagement\ApplicableAttributesResolver's shape but
 * scoped to a SINGLE category — a product carries exactly one, unlike an
 * opportunity's several product lines, so no cross-category merge is needed.
 * Reuses the SAME App\RequestManagement\ApplicableAttribute descriptor so the
 * shared AttributeValueValidator/AttributeValueNormalizer pipeline validates
 * and normalizes product values identically to opportunity ones.
 */
final class ProductAttributeResolver
{
    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * @return Collection<int, ApplicableAttribute>
     */
    public function resolve(Product $product): Collection
    {
        if ($product->category === null) {
            return collect();
        }

        return $this->hierarchy->effectiveAttributes($product->category, AttributeContext::Product)
            ->map(ApplicableAttribute::fromEffectiveAttributeRow(...))
            ->values();
    }
}
