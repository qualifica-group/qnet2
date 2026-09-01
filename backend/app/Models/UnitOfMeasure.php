<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\UnitOfMeasureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * UnitOfMeasure lookup entity (spec 0088, D-1): a lean, full-CRUD
 * classification (code/name/symbol/description) used to qualify a Product's
 * quantity, mirroring VatRate for leanness (no is_active/sort_order/reorder)
 * and PaymentMethod for the delete guard (D-7: both `products` and
 * `quoteLines` are referenced-by sets UnitOfMeasureService::delete() checks).
 * `code` is immutable after create (enforced in UpdateUnitOfMeasureRequest,
 * not here).
 */
#[Fillable(['name', 'code', 'symbol', 'description'])]
class UnitOfMeasure extends BaseModel
{
    /** @use HasFactory<UnitOfMeasureFactory> */
    use HasFactory, LogsModelActivity;

    // Eloquent's default pluralization of "UnitOfMeasure" is
    // "unit_of_measures"; the migration/spec 0088 use "units_of_measure"
    // (plural on "units", matching the resource slug), so the table name is
    // declared explicitly rather than renamed to fit the convention.
    protected $table = 'units_of_measure';

    /**
     * The Products classified with this unit — the FIRST referenced-by set
     * UnitOfMeasureService::delete() guards against (spec 0088, D-7).
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * The Quote lines whose unit was frozen to this row at write time (spec
     * 0088, D-5) — the SECOND referenced-by set the delete guard checks.
     *
     * @return HasMany<QuoteLine, $this>
     */
    public function quoteLines(): HasMany
    {
        return $this->hasMany(QuoteLine::class);
    }
}
