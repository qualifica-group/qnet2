<?php

namespace App\DataObjects\ProductCategories;

/**
 * Validated payload for creating a product category
 * (POST /api/product-categories, spec 0017). Declared DTO (no "magic flying
 * array") so the StoreProductCategoryRequest → ProductCategoryService
 * contract is explicit — see standards/architecture.md → Data Transfer
 * Objects.
 */
final readonly class CreateProductCategoryData
{
    /**
     * @param  array<int, array{attribute_id: int, context: string, is_required?: bool, sort_order?: int}>|null  $attributes
     */
    public function __construct(
        public string $name,
        public ?int $parentId = null,
        public bool $inheritsProductAttributes = true,
        public bool $inheritsOpportunityAttributes = true,
        public ?string $description = null,
        public ?array $attributes = null,
        public ?int $businessFunctionId = null,
    ) {}

    /**
     * Build from the validated StoreProductCategoryRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            parentId: array_key_exists('parent_id', $data) && $data['parent_id'] !== null ? (int) $data['parent_id'] : null,
            inheritsProductAttributes: array_key_exists('inherits_product_attributes', $data) ? (bool) $data['inherits_product_attributes'] : true,
            inheritsOpportunityAttributes: array_key_exists('inherits_opportunity_attributes', $data) ? (bool) $data['inherits_opportunity_attributes'] : true,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            attributes: array_key_exists('attributes', $data) ? (array) $data['attributes'] : null,
            businessFunctionId: array_key_exists('business_function_id', $data) && $data['business_function_id'] !== null ? (int) $data['business_function_id'] : null,
        );
    }

    public function hasAttributes(): bool
    {
        return $this->attributes !== null;
    }
}
