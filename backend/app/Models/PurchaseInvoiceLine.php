<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Database\Factories\PurchaseInvoiceLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Line of a payable document (spec 0250, D-5): it carries the cost type and the
 * work order of its amount. The amounts are written by the service (bcmath),
 * never by the client; the lines are audited through their document.
 */
#[Fillable([
    'purchase_invoice_id', 'purchase_order_line_id', 'product_id', 'description', 'quantity',
    'unit_price', 'vat_rate_id', 'cost_type_id', 'work_order_id', 'net_amount', 'vat_amount',
    'total_amount', 'sort_order',
])]
class PurchaseInvoiceLine extends BaseModel
{
    /** @use HasFactory<PurchaseInvoiceLineFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'sort_order' => 'int',
        ];
    }

    /**
     * @return BelongsTo<PurchaseInvoice, $this>
     */
    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    /**
     * @return BelongsTo<PurchaseOrderLine, $this>
     */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<VatRate, $this>
     */
    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }

    /**
     * @return BelongsTo<CostType, $this>
     */
    public function costType(): BelongsTo
    {
        return $this->belongsTo(CostType::class);
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
