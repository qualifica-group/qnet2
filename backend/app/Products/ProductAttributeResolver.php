<?php

declare(strict_types=1);

namespace App\Products;

use App\Enums\AttributeContext;
use App\Models\Product;
use App\RequestManagement\ApplicableAttribute;
use App\RequestManagement\AttributeSetResolver;
use Illuminate\Support\Collection;

/**
 * The product-level "applicable attributes" set (spec 0061): the EFFECTIVE
 * (own + inherited) Product-context attributes of the product's OWN category.
 * Thin caller of the generalized App\RequestManagement\AttributeSetResolver
 * (spec 0084, D-4) — scoped to a SINGLE category — a product carries exactly
 * one, unlike a quote's several offer lines, so no cross-category merge is
 * needed. Reuses the SAME App\RequestManagement\ApplicableAttribute
 * descriptor so the shared AttributeValueValidator/AttributeValueNormalizer
 * pipeline validates and normalizes product values identically to quote ones.
 */
final class ProductAttributeResolver
{
    public function __construct(private readonly AttributeSetResolver $setResolver) {}

    /**
     * @return Collection<int, ApplicableAttribute>
     */
    public function resolve(Product $product): Collection
    {
        if ($product->category === null) {
            return collect();
        }

        return $this->setResolver->resolve([$product->category->id], AttributeContext::Product);
    }
}
