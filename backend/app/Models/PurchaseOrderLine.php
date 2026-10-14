<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\PurchaseOrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Line of a purchase order (spec 0210). Only the editable content is fillable:
 * the amounts (D-12) and the RDA link (D-17) are written by services.
 */
#[Fillable([
    'position', 'product_id', 'description', 'cost_type_id', 'unit_of_measure_id',
    'quantity', 'unit_price', 'vat_rate_id', 'work_order_id',
])]
class PurchaseOrderLine extends BaseModel
{
    /** @use HasFactory<PurchaseOrderLineFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'position' => 'int',
            'quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'residual_closed_at' => 'datetime',
            'unit_price' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<CostType, $this>
     */
    public function costType(): BelongsTo
    {
        return $this->belongsTo(CostType::class);
    }

    /**
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    /**
     * @return BelongsTo<VatRate, $this>
     */
    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<PurchaseRequestLine, $this>
     */
    public function purchaseRequestLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestLine::class);
    }

    /**
     * @return HasMany<PurchaseOrderReceiptLine, $this>
     */
    public function receiptLines(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceiptLine::class);
    }

    /**
     * @return HasMany<PurchaseInvoiceLine, $this>
     */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function residualClosedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'residual_closed_by_id');
    }
}
