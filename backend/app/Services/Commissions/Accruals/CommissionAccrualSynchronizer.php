<?php

declare(strict_types=1);

namespace App\Services\Commissions\Accruals;

use App\Enums\CommissionAccrualType;
use App\Models\CommissionAccrual;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the commission accrual ledger aligned with the collections (spec 0212).
 * It is idempotent and append-only: for every bucket the difference between the
 * expected accrual and the sum of its movements is posted as an ACCRUAL or a
 * REVERSAL, never as an update. Called inside the transaction of the caller, so
 * a failure rolls the collection back too (D-5).
 */
class CommissionAccrualSynchronizer
{
    public function __construct(
        private readonly AccrualStateLoader $loader,
        private readonly CommissionShareCalculator $calculator,
    ) {}

    public function syncInvoice(Invoice $invoice, ?InvoiceInstallment $trigger = null): SyncResult
    {
        return DB::transaction(function () use ($invoice, $trigger): SyncResult {
            // Step 1: lock the invoices that share a quote line and load their buckets
            $state = $this->loader->load($invoice);

            // Step 2: post the difference of every bucket
            $result = new SyncResult;

            foreach ($state->buckets as $bucket) {
                $result = $result->plus($this->syncBucket($bucket, $state, $trigger));
            }

            return $result;
        });
    }

    private function syncBucket(AccrualBucket $bucket, AccrualState $state, ?InvoiceInstallment $trigger): SyncResult
    {
        $invoice = $state->invoices[$bucket->invoiceId];
        $total = (string) $invoice->total_amount;
        $key = $bucket->bucketKey();

        // A bucket without movements is a first synchronization: replay the history (D-4)
        if (! array_key_exists($key, $state->sums)) {
            return $this->replay($bucket, $invoice, $trigger);
        }

        $expected = $this->calculator->expected($bucket->share, $this->collectedUntil($invoice, null), $total);
        $delta = bcsub($expected, $state->sums[$key], 2);

        return match (bccomp($delta, '0', 2)) {
            1 => $this->post($bucket, $invoice, CommissionAccrualType::Accrual, $delta, $trigger?->collected_at?->toDateString() ?? $this->today(), $trigger?->id),
            -1 => $this->post($bucket, $invoice, CommissionAccrualType::Reversal, $delta, $this->today(), $trigger?->id),
            default => new SyncResult,
        };
    }

    /**
     * One ACCRUAL per distinct collection date, with the ratio cumulated to that
     * date, so the history lands in the right periods and the last one reaches
     * the expected amount exactly.
     */
    private function replay(AccrualBucket $bucket, Invoice $invoice, ?InvoiceInstallment $trigger): SyncResult
    {
        $dates = $this->collections($invoice)->groupBy(fn (InvoiceInstallment $row): string => $row->collected_at->toDateString())->sortKeys();
        $posted = '0.00';
        $result = new SyncResult;

        foreach ($dates as $date => $rows) {
            $expected = $this->calculator->expected($bucket->share, $this->collectedUntil($invoice, $date), (string) $invoice->total_amount);
            $delta = bcsub($expected, $posted, 2);

            if (bccomp($delta, '0', 2) <= 0) {
                continue;
            }

            $installmentId = $trigger?->collected_at?->toDateString() === $date ? $trigger->id : $rows->last()->id;
            $result = $result->plus($this->post($bucket, $invoice, CommissionAccrualType::Accrual, $delta, $date, $installmentId));
            $posted = $expected;
        }

        return $result;
    }

    /** Sum collected by the invoice up to `$date` inclusive (all of it when null). */
    private function collectedUntil(Invoice $invoice, ?string $date): string
    {
        return $this->collections($invoice)
            ->filter(fn (InvoiceInstallment $row): bool => $date === null || $row->collected_at->toDateString() <= $date)
            ->reduce(fn (string $sum, InvoiceInstallment $row): string => bcadd($sum, (string) $row->collected_amount, 2), '0.00');
    }

    /**
     * @return Collection<int, InvoiceInstallment>
     */
    private function collections(Invoice $invoice): Collection
    {
        return $invoice->installments->filter(
            fn (InvoiceInstallment $row): bool => $row->collected_at !== null && bccomp((string) $row->collected_amount, '0', 2) > 0,
        );
    }

    private function post(AccrualBucket $bucket, Invoice $invoice, CommissionAccrualType $type, string $amount, string $accruedOn, ?int $installmentId): SyncResult
    {
        CommissionAccrual::query()->create([
            'invoice_id' => $bucket->invoiceId,
            'quote_line_id' => $bucket->quoteLineId,
            'quote_line_commission_id' => $bucket->commissionId,
            'invoice_installment_id' => $installmentId,
            'company_id' => $invoice->company_id,
            'recipient_role' => $bucket->role,
            'recipient_type' => $bucket->recipientType,
            'recipient_id' => $bucket->recipientId,
            'type' => $type,
            'base_amount' => $bucket->share,
            'amount' => $amount,
            'accrued_on' => $accruedOn,
        ]);

        return $type === CommissionAccrualType::Accrual ? new SyncResult(accruals: 1) : new SyncResult(reversals: 1);
    }

    private function today(): string
    {
        return now()->toDateString();
    }
}
