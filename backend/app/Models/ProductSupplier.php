<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Database\Factories\ProductSupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One supplier of a Product with its own selling price and cost (spec 0320,
 * D-1, rev. 2 D-14: the product's usages decide which one applies): `is_default`
 * flags the supplier proposed on the lines (exactly one when the product has
 * suppliers). No activity log on this row (pure child collection of the
 * Product, which already logs its own changes).
 */
#[Fillable(['product_id', 'supplier_id', 'price', 'cost', 'is_default'])]
class ProductSupplier extends BaseModel
{
    /** @use HasFactory<ProductSupplierFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'is_default' => 'bool',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Registry::class, 'supplier_id');
    }
}
