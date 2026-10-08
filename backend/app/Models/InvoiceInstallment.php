<?php

namespace App\Models;

use App\Enums\InstallmentStatus;
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
    'collected_amount', 'collected_at', 'redistribution_snapshot',
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
            ->logOnly(['due_date', 'payment_method_code', 'collected_amount', 'collected_at'])
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
            'redistribution_snapshot' => 'array',
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

        return bccomp($collected, '0', 2) > 0 ? InstallmentStatus::Paid : InstallmentStatus::Unpaid;
    }

    /** Amount still to collect; negative when more than the amount was collected. */
    public function residualAmount(): string
    {
        return bcsub((string) $this->amount, (string) ($this->collected_amount ?? '0'), 2);
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
