<?php

namespace App\Models;

use App\Enums\PurchaseInstallmentStatus;
use App\Enums\WriteOffReason;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Carbon\CarbonInterface;
use Database\Factories\PurchaseInvoiceInstallmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;

/**
 * An installment of a PurchaseInvoice schedule with its payment state (spec
 * 0211, D-4): `status()` is derived from paid_amount, never persisted.
 */
#[Fillable([
    'purchase_invoice_id', 'sequence', 'due_date', 'amount', 'payment_method_code',
    'paid_amount', 'paid_at', 'financial_account_id', 'redistribution_snapshot',
    'write_off_amount', 'write_off_reason', 'write_off_note', 'reconciled_at', 'reconciled_by', 'compensation_id',
])]
class PurchaseInvoiceInstallment extends BaseModel
{
    /** @use HasFactory<PurchaseInvoiceInstallmentFactory> */
    use HasFactory;

    use LogsModelActivity;

    /**
     * Audit only what a user changes on an installment (due date, payment
     * method code, payment); amounts move with the redistribution, not by hand.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['due_date', 'payment_method_code', 'paid_amount', 'paid_at', 'financial_account_id', 'write_off_amount', 'write_off_reason', 'write_off_note', 'reconciled_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->getTable());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'int',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'paid_at' => 'date',
            'write_off_amount' => 'decimal:2',
            'write_off_reason' => WriteOffReason::class,
            'redistribution_snapshot' => 'array',
            'reconciled_at' => 'datetime',
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
     * @return BelongsTo<InstallmentCompensation, $this>
     */
    public function compensation(): BelongsTo
    {
        return $this->belongsTo(InstallmentCompensation::class);
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function status(): PurchaseInstallmentStatus
    {
        return $this->isPaid() ? PurchaseInstallmentStatus::Paid : PurchaseInstallmentStatus::Unpaid;
    }

    public function isPaid(): bool
    {
        return bccomp((string) ($this->paid_amount ?? '0'), '0', 2) > 0
            || bccomp((string) ($this->write_off_amount ?? '0'), '0', 2) > 0;
    }

    /** Amount still to pay (after the write-off); negative when more than the amount was paid. */
    public function residualAmount(): string
    {
        $settled = bcadd((string) ($this->paid_amount ?? '0'), (string) ($this->write_off_amount ?? '0'), 2);

        return bcsub((string) $this->amount, $settled, 2);
    }

    /** Whole days an open installment is past its due date, 0 when paid or not yet due. */
    public function daysOverdue(?CarbonInterface $today = null): int
    {
        $today ??= Carbon::today();

        if ($this->isPaid() || ! $this->due_date->lt($today)) {
            return 0;
        }

        return (int) $this->due_date->diffInDays($today, true);
    }
}
