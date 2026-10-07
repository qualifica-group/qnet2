<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\WorkOrderLinePaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment data of one commessa line (spec 0201, D-3/D-11): status, payment
 * agreement and unpaid flag. One row per programmed offer line, created on
 * the first save and deleted when the line leaves the commessa. Audited
 * (LogsModelActivity): the legacy logs every status change.
 */
#[Fillable(['work_order_id', 'quote_line_id', 'work_order_payment_status_id', 'payment_agreement', 'has_unpaid'])]
class WorkOrderLinePayment extends BaseModel
{
    /** @use HasFactory<WorkOrderLinePaymentFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['has_unpaid' => 'bool'];
    }

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** @return BelongsTo<QuoteLine, $this> */
    public function quoteLine(): BelongsTo
    {
        return $this->belongsTo(QuoteLine::class);
    }

    /** @return BelongsTo<WorkOrderPaymentStatus, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(WorkOrderPaymentStatus::class, 'work_order_payment_status_id');
    }
}
