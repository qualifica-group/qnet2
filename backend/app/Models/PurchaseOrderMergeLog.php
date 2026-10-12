<?php

namespace App\Models;

use App\Enums\PurchaseOrderMergeAction;
use Database\Factories\PurchaseOrderMergeLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One merge of a source purchase order into a destination (spec 0210, D-19).
 * Append-only: written by PurchaseOrderMergeService and never updated.
 */
#[Fillable([
    'destination_purchase_order_id', 'source_purchase_order_id', 'source_code',
    'source_snapshot', 'moved_line_ids', 'source_action', 'performed_by',
])]
class PurchaseOrderMergeLog extends Model
{
    /** @use HasFactory<PurchaseOrderMergeLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'source_snapshot' => 'array',
            'moved_line_ids' => 'array',
            'source_action' => PurchaseOrderMergeAction::class,
        ];
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'destination_purchase_order_id');
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'source_purchase_order_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
