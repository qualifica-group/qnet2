<?php

namespace App\Models;

use App\Enums\PurchaseInstallmentStatus;
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
            ->logOnly(['due_date', 'payment_method_code', 'paid_amount', 'paid_at', 'financial_account_id'])
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
            'redistribution_snapshot' => 'array',
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
        return bccomp((string) ($this->paid_amount ?? '0'), '0', 2) > 0;
    }

    /** Amount still to pay; negative when more than the amount was paid. */
    public function residualAmount(): string
    {
        return bcsub((string) $this->amount, (string) ($this->paid_amount ?? '0'), 2);
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
