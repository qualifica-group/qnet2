<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\PurchaseOrderReceiptLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quantity received for one order line within a receipt (spec 0254, D-1).
 */
#[Fillable(['purchase_order_line_id', 'quantity'])]
class PurchaseOrderReceiptLine extends BaseModel
{
    /** @use HasFactory<PurchaseOrderReceiptLineFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    /**
     * @return BelongsTo<PurchaseOrderReceipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderReceipt::class, 'purchase_order_receipt_id');
    }

    /**
     * @return BelongsTo<PurchaseOrderLine, $this>
     */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }
}
