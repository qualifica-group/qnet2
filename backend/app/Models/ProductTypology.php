<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ProductTypologyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ProductTypology lookup entity (spec 0099, D-1/D-2): a lean, full-CRUD
 * classification (code/name/description) of a Product, mirroring
 * UnitOfMeasure for leanness (no is_active/sort_order/reorder) and its
 * delete guard shape.
 *
 * Deliberately NOT named ProductType (D-1): App\Enums\ProductType already
 * owns that name for the pre-existing `products.product_type` enum column,
 * which this module leaves untouched.
 *
 * `code` is immutable after create (enforced in UpdateProductTypologyRequest,
 * not here). Unlike UnitOfMeasure this row is NOT frozen onto quote lines
 * (D-5): the typology classifies the Product and is read live through it, so
 * `products` is the ONLY referenced-by set the delete guard checks (D-8).
 */
#[Fillable(['name', 'code', 'description'])]
class ProductTypology extends BaseModel
{
    /** @use HasFactory<ProductTypologyFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * The Products classified with this typology — the ONLY referenced-by set
     * ProductTypologyService::delete() guards against (spec 0099, D-8).
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
