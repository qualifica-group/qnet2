<?php

namespace App\Models;

use App\Enums\InstallmentStatus;
use App\Enums\WriteOffReason;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Carbon\CarbonInterface;
use Database\Factories\InvoiceInstallmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;

/**
 * An installment of an Invoice schedule with its collection state (spec 0194,
 * D-13): `status()` is derived from collected_amount vs amount, never persisted.
 */
#[Fillable([
    'invoice_id', 'sequence', 'due_date', 'amount', 'payment_method_code',
    'collected_amount', 'collected_at', 'redistribution_snapshot', 'financial_account_id',
    'write_off_amount', 'write_off_reason', 'write_off_note', 'reconciled_at', 'reconciled_by', 'compensation_id',
])]
class InvoiceInstallment extends BaseModel
{
    /** @use HasFactory<InvoiceInstallmentFactory> */
    use HasFactory;

    use LogsModelActivity;

    /**
     * Audit only what a user changes on an installment (due date, payment method
     * code, collection); amounts move with the invoice rebalancing, not by hand
     * (spec 0197, D-7).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['due_date', 'payment_method_code', 'collected_amount', 'collected_at', 'financial_account_id', 'write_off_amount', 'write_off_reason', 'write_off_note', 'reconciled_at'])
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
            'collected_amount' => 'decimal:2',
            'collected_at' => 'date',
            'write_off_amount' => 'decimal:2',
            'write_off_reason' => WriteOffReason::class,
            'redistribution_snapshot' => 'array',
            'reconciled_at' => 'datetime',
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

    public function status(): InstallmentStatus
    {
        $collected = bccomp((string) ($this->collected_amount ?? '0'), '0', 2) > 0;
        $writtenOff = bccomp((string) ($this->write_off_amount ?? '0'), '0', 2) > 0;

        return $collected || $writtenOff ? InstallmentStatus::Paid : InstallmentStatus::Unpaid;
    }

    /** Amount still to collect (after the write-off); negative when more than the amount was collected. */
    public function residualAmount(): string
    {
        $settled = bcadd((string) ($this->collected_amount ?? '0'), (string) ($this->write_off_amount ?? '0'), 2);

        return bcsub((string) $this->amount, $settled, 2);
    }

    /** Whole days an open installment is past its due date, 0 when paid or not yet due. */
    public function daysOverdue(?CarbonInterface $today = null): int
    {
        $today ??= Carbon::today();

        if ($this->status() === InstallmentStatus::Paid || ! $this->due_date->lt($today)) {
            return 0;
        }

        return (int) $this->due_date->diffInDays($today, true);
    }
}
