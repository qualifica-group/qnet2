<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Product (spec 0017): generic fields (name/description/cost/price/category/
 * product_type) plus its own attribute VALUES (spec 0061). `attribute_values`
 * (json, keyed by Attribute `code`) mirrors Opportunity.attribute_values: it
 * holds the product's values against the PRODUCT-context effective
 * attributes of its category (App\Products\ProductAttributeResolver) — the
 * `attributes` catalogue (Attribute/ProductCategory) itself stays a reusable
 * template. Deliberately absent from #[Fillable] (mass-assignment guard,
 * same discipline as Opportunity): written exclusively via
 * App\Services\ProductService, which forceFill()s it after validation.
 *
 * `code` (spec 0065, D-1/D-1b) is likewise deliberately absent from
 * #[Fillable]: it is writable only at create time (manual value or the
 * PRD-0001 sequential fallback) and permanently read-only afterwards, so
 * ProductService assigns it directly AFTER mass-assignment, mirroring
 * Project/Campaign (spec 0025).
 *
 * `unit_of_measure_id` (spec 0088, D-4) is NOT NULL: when absent/null from a
 * write, ProductService resolves the default unit (`code='unit'`) so the
 * column is always populated without being a `mandatory` field-permission.
 * `product_typology_id` (spec 0099, D-3) is the exact same arrangement,
 * defaulting to the `code='institution'` typology.
 */
#[Fillable(['name', 'description', 'cost', 'price', 'category_id', 'product_type', 'vat_rate_id', 'supplier_id', 'unit_of_measure_id', 'product_typology_id'])]
class Product extends BaseModel
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'price' => 'decimal:2',
            'product_type' => ProductType::class,
            'attribute_values' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class, 'vat_rate_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Registry::class, 'supplier_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    public function productTypology(): BelongsTo
    {
        return $this->belongsTo(ProductTypology::class);
    }
}
