<?php

namespace App\DataObjects\Products;

use App\Enums\ProductType;

/**
 * Validated payload for a partial (PATCH) product update
 * (PUT/PATCH /api/products/{product}, spec 0017; spec 0061 for
 * `attributeValues`).
 *
 * Declared DTO (no "magic flying array") so the UpdateProductRequest →
 * ProductService contract is explicit. `description`/`cost`/`price`/
 * `category_id` are all legitimately nullable-or-changeable VALUES, so a
 * plain null property cannot distinguish "not submitted" from "submitted as
 * null" — the `*Submitted` flags carry that distinction, mirroring
 * UpdateBusinessFunctionData/UpdateProductCategoryData. `attributeValues`
 * mirrors `attributes` on UpdateProductCategoryData: an array or absent,
 * never a legitimate literal null, so `hasAttributeValues()` alone
 * disambiguates without a `*Submitted` flag.
 */
final readonly class UpdateProductData
{
    /**
     * @param  array<string, mixed>|null  $attributeValues
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public bool $descriptionSubmitted = false,
        public ?float $cost = null,
        public bool $costSubmitted = false,
        public ?float $price = null,
        public bool $priceSubmitted = false,
        public ?int $categoryId = null,
        public bool $categoryIdSubmitted = false,
        public ?ProductType $productType = null,
        public bool $productTypeSubmitted = false,
        public ?int $vatRateId = null,
        public bool $vatRateIdSubmitted = false,
        public ?int $supplierId = null,
        public bool $supplierIdSubmitted = false,
        public ?array $attributeValues = null,
    ) {}

    /**
     * Build from the validated UpdateProductRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            descriptionSubmitted: array_key_exists('description', $data),
            cost: array_key_exists('cost', $data) && $data['cost'] !== null ? (float) $data['cost'] : null,
            costSubmitted: array_key_exists('cost', $data),
            price: array_key_exists('price', $data) && $data['price'] !== null ? (float) $data['price'] : null,
            priceSubmitted: array_key_exists('price', $data),
            categoryId: array_key_exists('category_id', $data) && $data['category_id'] !== null ? (int) $data['category_id'] : null,
            categoryIdSubmitted: array_key_exists('category_id', $data),
            productType: array_key_exists('product_type', $data) && $data['product_type'] !== null ? ProductType::from((string) $data['product_type']) : null,
            productTypeSubmitted: array_key_exists('product_type', $data),
            vatRateId: array_key_exists('vat_rate_id', $data) && $data['vat_rate_id'] !== null ? (int) $data['vat_rate_id'] : null,
            vatRateIdSubmitted: array_key_exists('vat_rate_id', $data),
            supplierId: array_key_exists('supplier_id', $data) && $data['supplier_id'] !== null ? (int) $data['supplier_id'] : null,
            supplierIdSubmitted: array_key_exists('supplier_id', $data),
            attributeValues: array_key_exists('attribute_values', $data) ? (array) $data['attribute_values'] : null,
        );
    }

    public function hasAttributeValues(): bool
    {
        return $this->attributeValues !== null;
    }

    /**
     * Only the plain scalar attributes the client actually submitted, ready
     * for a partial mass-assignment update.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }

        if ($this->descriptionSubmitted) {
            $attributes['description'] = $this->description;
        }

        if ($this->costSubmitted) {
            $attributes['cost'] = $this->cost;
        }

        if ($this->priceSubmitted) {
            $attributes['price'] = $this->price;
        }

        if ($this->categoryIdSubmitted) {
            $attributes['category_id'] = $this->categoryId;
        }

        if ($this->productTypeSubmitted) {
            $attributes['product_type'] = $this->productType;
        }

        if ($this->vatRateIdSubmitted) {
            $attributes['vat_rate_id'] = $this->vatRateId;
        }

        if ($this->supplierIdSubmitted) {
            $attributes['supplier_id'] = $this->supplierId;
        }

        return $attributes;
    }
}
