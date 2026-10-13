<?php

namespace App\Models;

use App\Enums\CommissionAccrualType;
use App\Enums\CommissionRecipientRole;
use App\Models\Abstracts\BaseModel;
use Database\Factories\CommissionAccrualFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One movement of the commission accrual ledger (spec 0212). Rows are never
 * updated or deleted: corrections are new REVERSAL/ACCRUAL rows, so the model
 * carries no activity log (the ledger is its own audit trail).
 */
#[Fillable([
    'invoice_id', 'quote_line_id', 'quote_line_commission_id', 'invoice_installment_id',
    'recipient_role', 'recipient_type', 'recipient_id', 'type', 'base_amount', 'amount',
    'accrued_on', 'settled_externally_on',
])]
class CommissionAccrual extends BaseModel
{
    /** @use HasFactory<CommissionAccrualFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => CommissionAccrualType::class,
            'recipient_role' => CommissionRecipientRole::class,
            'base_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'accrued_on' => 'date',
            'settled_externally_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<QuoteLine, $this>
     */
    public function quoteLine(): BelongsTo
    {
        return $this->belongsTo(QuoteLine::class);
    }

    /**
     * @return BelongsTo<QuoteLineCommission, $this>
     */
    public function quoteLineCommission(): BelongsTo
    {
        return $this->belongsTo(QuoteLineCommission::class);
    }

    /**
     * @return BelongsTo<InvoiceInstallment, $this>
     */
    public function invoiceInstallment(): BelongsTo
    {
        return $this->belongsTo(InvoiceInstallment::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }
}
