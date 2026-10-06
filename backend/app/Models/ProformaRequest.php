<?php

namespace App\Models;

use App\Enums\ProformaRequestKind;
use App\Enums\ProformaRequestStatus;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasNotes;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ProformaRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to Accounting to issue a proforma for a work order (spec 0193).
 * Only `note` is editable after creation; `status` is driven by the future
 * invoicing flow (D-7).
 */
#[Fillable([
    'work_order_id', 'kind', 'supplier_id', 'payment_method_id', 'status',
    'issued_at', 'note', 'assigned_to', 'assigned_by',
])]
class ProformaRequest extends BaseModel
{
    /** @use HasFactory<ProformaRequestFactory> */
    use HasFactory, HasNotes, LogsModelActivity;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'kind' => ProformaRequestKind::class,
            'status' => ProformaRequestStatus::class,
            'issued_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<Registry, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Registry::class, 'supplier_id');
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
