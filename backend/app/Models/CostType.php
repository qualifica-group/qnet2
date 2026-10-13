<?php

namespace App\Models;

use App\Enums\CostTypeNature;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\CostTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cost type lookup (spec 0210, D-3): classifies every purchase order line.
 * Configurable, with an active flag and a display order.
 */
#[Fillable(['name', 'description', 'nature', 'color', 'sort_order', 'is_active'])]
class CostType extends BaseModel
{
    /** @use HasFactory<CostTypeFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nature' => CostTypeNature::class,
            'sort_order' => 'int',
            'is_active' => 'bool',
        ];
    }

    /**
     * @return HasMany<PurchaseOrderLine, $this>
     */
    public function purchaseOrderLines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /**
     * @return HasMany<PurchaseInvoice, $this>
     */
    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<WorkOrderCost, $this>
     */
    public function workOrderCosts(): HasMany
    {
        return $this->hasMany(WorkOrderCost::class);
    }
}
