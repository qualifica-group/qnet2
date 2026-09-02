<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTypology;
use App\Models\Registry;
use App\Models\UnitOfMeasure;
use App\Models\VatRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * $effectiveBusinessFunction is resolved by ProductService (spec 0023)
     * and passed in explicitly — never computed here — because it requires
     * CategoryHierarchy's ancestor walk, which stays out of the Resource
     * layer (Controller thin -> Service authoritative -> Resource pure
     * output shape). $applicableAttributes (spec 0061) is the SAME kind of
     * precomputed input, from ProductService::applicableAttributes().
     *
     * @param  array{id: int, name: string}|null  $effectiveBusinessFunction
     * @param  array<int, array<string, mixed>>  $applicableAttributes
     * @param  array{sections: array<int, array<string, mixed>>}|null  $attributeLayout
     */
    public function __construct(
        Product $resource,
        private readonly ?array $effectiveBusinessFunction = null,
        private readonly array $applicableAttributes = [],
        private readonly ?array $attributeLayout = null,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'cost' => $this->cost,
            'price' => $this->price,
            'category_id' => $this->category_id,
            'category' => $this->categorySummary($this->category),
            'product_type' => $this->product_type,
            'vat_rate_id' => $this->vat_rate_id,
            'vat_rate' => $this->vatRateSummary($this->vatRate),
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->supplierSummary($this->supplier),
            // Spec 0088, D-4: always populated (NOT NULL, defaulted server-side).
            'unit_of_measure_id' => $this->unit_of_measure_id,
            'unit_of_measure' => $this->unitOfMeasureSummary($this->unitOfMeasure),
            // Spec 0099, D-3: always populated (NOT NULL, defaulted server-side).
            'product_typology_id' => $this->product_typology_id,
            'product_typology' => $this->productTypologySummary($this->productTypology),
            // Read-only, derived from the category (spec 0023): never
            // writable via POST/PATCH (not in $fillable, no FormRequest rule).
            'business_function' => $this->effectiveBusinessFunction,
            // Spec 0061: the product's own values against its category's
            // PRODUCT-context effective attributes, and that same set
            // (`applicable_attributes`) for the create/edit form's dynamic
            // fields — mirrors the request-management work panel's shape.
            'attribute_values' => $this->attribute_values ?? [],
            'applicable_attributes' => $this->applicableAttributes,
            // Spec 0062: the category's configured (context=product,
            // form_mode=view) layout, additive — null falls back to the
            // pre-existing flat rendering (AC-007).
            'attribute_layout' => $this->attributeLayout,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function categorySummary(?ProductCategory $category): ?array
    {
        if ($category === null) {
            return null;
        }

        return ['id' => $category->id, 'name' => $category->name];
    }

    /**
     * @return array{id: int, name: string, rate: mixed}|null
     */
    private function vatRateSummary(?VatRate $vatRate): ?array
    {
        if ($vatRate === null) {
            return null;
        }

        return ['id' => $vatRate->id, 'name' => $vatRate->name, 'rate' => $vatRate->rate];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function supplierSummary(?Registry $supplier): ?array
    {
        if ($supplier === null) {
            return null;
        }

        return ['id' => $supplier->id, 'name' => $supplier->name];
    }

    /**
     * @return array{id: int, name: string, symbol: string}|null
     */
    private function unitOfMeasureSummary(?UnitOfMeasure $unitOfMeasure): ?array
    {
        if ($unitOfMeasure === null) {
            return null;
        }

        return ['id' => $unitOfMeasure->id, 'name' => $unitOfMeasure->name, 'symbol' => $unitOfMeasure->symbol];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function productTypologySummary(?ProductTypology $productTypology): ?array
    {
        if ($productTypology === null) {
            return null;
        }

        return ['id' => $productTypology->id, 'name' => $productTypology->name];
    }
}
