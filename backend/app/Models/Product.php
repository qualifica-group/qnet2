<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Enums\ProductUsage;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
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
 *
 * `usages` (spec 0142) is the set of App\Enums\ProductUsage values deciding
 * which Offerta tab may pick the product; it defaults to Sellable only.
 */
#[Fillable(['name', 'description', 'cost', 'price', 'category_id', 'product_type', 'usages', 'vat_rate_id', 'supplier_id', 'unit_of_measure_id', 'product_typology_id'])]
class Product extends BaseModel
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * Spec 0142, D-3: a product created without an explicit choice is
     * Sellable only.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'usages' => '["'.ProductUsage::Sale->value.'"]',
    ];

    /**
     * Spec 0142: `usages` is stored in canonical case order, so one set is
     * always the same JSON string — what the products grid sorts on.
     */
    protected static function booted(): void
    {
        static::saving(static function (Product $product): void {
            $usages = $product->usages;

            if ($usages !== null) {
                $product->usages = collect(ProductUsage::cases())->filter(static fn (ProductUsage $usage): bool => $usages->contains($usage))->values();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'price' => 'decimal:2',
            'product_type' => ProductType::class,
            'usages' => AsEnumCollection::of(ProductUsage::class),
            'attribute_values' => 'array',
        ];
    }

    public function isUsableAs(ProductUsage $usage): bool
    {
        return $this->usages?->contains($usage) ?? false;
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
