<?php

namespace App\DataObjects\Products;

use App\Enums\ProductType;

/**
 * Validated payload for creating a product (POST /api/products, spec 0017;
 * spec 0061 for `attributeValues`; spec 0065, D-1b for `code`). Declared DTO
 * (no "magic flying array") so the StoreProductRequest → ProductService
 * contract is explicit — see standards/architecture.md → Data Transfer
 * Objects. `cost`/`price`/`productType` are all required by the FormRequest,
 * so they cross as non-null values. `code` is optional: absent/null/empty
 * means the Service falls back to the sequential PRD-0001 generator.
 * `unitOfMeasureId` (spec 0088, D-4) is likewise optional: absent OR null
 * both collapse to the Service falling back to the default unit — on
 * create there is no persisted value to "leave untouched", so the two cases
 * need no `*Submitted` flag (unlike UpdateProductData).
 */
final readonly class CreateProductData
{
    /**
     * @param  array<string, mixed>|null  $attributeValues
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public float $cost,
        public float $price,
        public int $categoryId,
        public ProductType $productType,
        public ?int $vatRateId = null,
        public ?int $supplierId = null,
        public ?int $unitOfMeasureId = null,
        public ?array $attributeValues = null,
        public ?string $code = null,
    ) {}

    /**
     * Build from the validated StoreProductRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            description: array_key_exists('description', $data) ? $data['description'] : null,
            cost: (float) $data['cost'],
            price: (float) $data['price'],
            categoryId: (int) $data['category_id'],
            productType: ProductType::from((string) $data['product_type']),
            vatRateId: array_key_exists('vat_rate_id', $data) && $data['vat_rate_id'] !== null ? (int) $data['vat_rate_id'] : null,
            supplierId: array_key_exists('supplier_id', $data) && $data['supplier_id'] !== null ? (int) $data['supplier_id'] : null,
            unitOfMeasureId: array_key_exists('unit_of_measure_id', $data) && $data['unit_of_measure_id'] !== null ? (int) $data['unit_of_measure_id'] : null,
            attributeValues: array_key_exists('attribute_values', $data) ? (array) $data['attribute_values'] : null,
            code: array_key_exists('code', $data) && $data['code'] !== null && $data['code'] !== '' ? (string) $data['code'] : null,
        );
    }

    public function hasAttributeValues(): bool
    {
        return $this->attributeValues !== null;
    }
}
