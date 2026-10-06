<?php

namespace App\Models;

use App\Enums\InstallmentStatus;
use App\Models\Abstracts\BaseModel;
use Database\Factories\InvoiceInstallmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An installment of an Invoice schedule with its collection state (spec 0194,
 * D-13): `status()` is derived from collected_amount vs amount, never persisted.
 */
#[Fillable([
    'invoice_id', 'sequence', 'due_date', 'amount', 'payment_method_code',
    'collected_amount', 'collected_at',
])]
class InvoiceInstallment extends BaseModel
{
    /** @use HasFactory<InvoiceInstallmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'int',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'collected_amount' => 'decimal:2',
            'collected_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function status(): InstallmentStatus
    {
        $collected = (string) ($this->collected_amount ?? '0');

        if (bccomp($collected, '0', 2) <= 0) {
            return InstallmentStatus::Unpaid;
        }

        return bccomp($collected, (string) $this->amount, 2) >= 0
            ? InstallmentStatus::Paid
            : InstallmentStatus::PartiallyPaid;
    }
}
