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
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * A request to Accounting to issue a proforma for a work order (spec 0193).
 * Only `note` is editable after creation; `status` is driven by the future
 * invoicing flow (D-7). Spec 0267 (D-6): `quote_id` is the offer the request is
 * raised for.
 */
#[Fillable([
    'work_order_id', 'quote_id', 'kind', 'supplier_id', 'payment_method_id', 'status',
    'issued_at', 'note', 'assigned_to', 'assigned_by',
])]
class ProformaRequest extends BaseModel
{
    /** @use HasFactory<ProformaRequestFactory> */
    use HasFactory, HasNotes, LogsModelActivity;

    /**
     * A mono-offer work order implies the offer, so existing creators need not
     * pass `quote_id`; a multi-offer one has none and the caller must choose.
     */
    protected static function booted(): void
    {
        static::creating(static function (self $request): void {
            $request->quote_id ??= WorkOrder::query()->whereKey($request->work_order_id)->value('quote_id');

            if ($request->quote_id === null) {
                throw new LogicException('quote_id is required for a proforma request of a multi-quote work order');
            }
        });
    }

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
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * The document issued from this request (spec 0194, D-7).
     *
     * @return HasOne<Invoice, $this>
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * Deletable only while it is pending and no document was issued from it
     * (spec 0243, D-4). Single predicate shared by service, table and authorization.
     */
    public function isDeletable(): bool
    {
        return $this->status === ProformaRequestStatus::Pending
            && $this->loadMissing('invoice')->invoice === null;
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
