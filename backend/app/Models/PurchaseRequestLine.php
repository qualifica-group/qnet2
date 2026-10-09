<?php

namespace App\Models;

use App\Enums\PurchaseRequestLineStatus;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\PurchaseRequestLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Line of a purchase request (spec 0208). Only the editable content is
 * fillable: amounts (D-12), status and approval (D-9) are written by services.
 */
#[Fillable([
    'position', 'product_id', 'description', 'reason', 'unit_of_measure_id',
    'quantity', 'unit_price', 'vat_rate_id',
])]
class PurchaseRequestLine extends BaseModel
{
    /** @use HasFactory<PurchaseRequestLineFactory> */
    use HasAttachments, HasFactory, LogsModelActivity;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'position' => 'int',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'status' => PurchaseRequestLineStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PurchaseRequest, $this>
     */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<PurchaseRequestLineStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(PurchaseRequestLineStatusLog::class)->latest('id');
    }
}
