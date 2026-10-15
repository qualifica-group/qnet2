<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\DataObjects\Ledger\LedgerEntry;
use App\DataObjects\Ledger\LedgerInstallmentEntry;
use App\Services\Invoices\InvoicePaymentStatusResolver;
use Carbon\CarbonImmutable;

/**
 * Pure ledger arithmetic of one section (spec 0266): running balance, opening
 * balance, summary and aging. No database, no clock: entries come in, "today"
 * is a parameter. Everything is summed in integer cents and converted to a
 * 2-decimal number only on output, so the running balance never drifts.
 */
final class LedgerCalculator
{
    private const int CENTS = 100;

    /** Upper bound in days (inclusive) => bucket, for overdue installments. */
    private const array OVERDUE_BUCKETS = [30 => 'overdue_1_30', 60 => 'overdue_31_60', 90 => 'overdue_61_90'];

    private const string OVERDUE_OVER = 'overdue_over_90';

    /** Upper bound in days (inclusive, today counts) => bucket, for installments not yet due. */
    private const array DUE_BUCKETS = [30 => 'due_0_30', 60 => 'due_31_60'];

    private const string DUE_OVER = 'due_over_60';

    public function __construct(private readonly InvoicePaymentStatusResolver $statusResolver) {}

    /**
     * @param  array<int, LedgerEntry>  $entries  any order; documents after `$to` are ignored
     * @return array{opening_balance: float, summary: array<string, mixed>, documents: array<int, array<string, mixed>>}
     */
    public function section(array $entries, LedgerSide $side, ?CarbonImmutable $from, ?CarbonImmutable $to, CarbonImmutable $today): array
    {
        // Step 1: drop what is after the period and order by date, then id
        $entries = $this->ordered(array_filter($entries, fn (LedgerEntry $entry): bool => $to === null || $entry->documentDate->lte($to)));

        // Step 2: split the history before the period (opening balance) from the period
        $openingCents = 0;
        $period = [];
        foreach ($entries as $entry) {
            if ($from !== null && $entry->documentDate->lt($from)) {
                $openingCents += $entry->residualCents();
            } else {
                $period[] = $entry;
            }
        }

        // Step 3: documents of the period with the running balance
        $running = $openingCents;
        $documents = [];
        foreach ($period as $entry) {
            $running += $entry->residualCents();
            $documents[] = $this->document($entry, $side, $running, $today);
        }

        // Step 4: summary; the aging also covers the open installments of the opening documents
        return [
            'opening_balance' => $this->money($openingCents),
            'summary' => $this->summary($period, $entries, $running, $today),
            'documents' => $documents,
        ];
    }

    /**
     * @param  array<int, LedgerEntry>  $entries
     * @return array<int, LedgerEntry>
     */
    private function ordered(array $entries): array
    {
        $entries = array_values($entries);
        usort($entries, fn (LedgerEntry $a, LedgerEntry $b): int => [$a->documentDate->toDateString(), $a->id] <=> [$b->documentDate->toDateString(), $b->id]);

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private function document(LedgerEntry $entry, LedgerSide $side, int $runningCents, CarbonImmutable $today): array
    {
        $settled = $entry->settledCents();
        $customer = $side === LedgerSide::Customer;

        return [
            'id' => $entry->id,
            'document_type' => $entry->documentType,
            'number_label' => $entry->numberLabel,
            'external_number' => $entry->externalNumber,
            'document_date' => $entry->documentDate->toDateString(),
            'company' => $entry->company,
            'company_site' => $entry->companySite,
            'operational_site' => $entry->operationalSite,
            'debit' => $this->money($customer ? $entry->totalCents : $settled),
            'credit' => $this->money($customer ? $settled : $entry->totalCents),
            'settled_amount' => $this->money($settled),
            'residual_amount' => $this->money($entry->residualCents()),
            'running_balance' => $this->money($runningCents),
            'status' => $this->status($entry, $today),
            'installments' => array_map(fn (LedgerInstallmentEntry $row): array => $this->installment($row, $today), $entry->installments),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function installment(LedgerInstallmentEntry $row, CarbonImmutable $today): array
    {
        return [
            'id' => $row->id,
            'sequence' => $row->sequence,
            'due_date' => $row->dueDate->toDateString(),
            'amount' => $this->money($row->amountCents),
            'settled_amount' => $row->settledCents === null ? null : $this->money($row->settledCents),
            'settled_at' => $row->settledAt?->toDateString(),
            'residual_amount' => $this->money($row->residualCents()),
            'days_late' => $this->daysLate($row, $today),
        ];
    }

    private function daysLate(LedgerInstallmentEntry $row, CarbonImmutable $today): int
    {
        if ($row->residualCents() <= 0 || ! $row->dueDate->lt($today)) {
            return 0;
        }

        return (int) $row->dueDate->diffInDays($today, true);
    }

    /** Same rule as the active invoice status: paid, else the state of the first open installment. */
    private function status(LedgerEntry $entry, CarbonImmutable $today): string
    {
        if ($entry->installments !== [] && $entry->residualCents() <= 0) {
            return 'paid';
        }

        foreach ($entry->installments as $row) {
            if ($row->residualCents() > 0) {
                return $this->statusResolver->forDueDate($row->dueDate, $today)->value;
            }
        }

        return 'not_due';
    }

    /**
     * @param  array<int, LedgerEntry>  $period
     * @param  array<int, LedgerEntry>  $upToPeriodEnd
     * @return array<string, mixed>
     */
    private function summary(array $period, array $upToPeriodEnd, int $residualCents, CarbonImmutable $today): array
    {
        $aging = $this->aging($upToPeriodEnd, $today);
        $overdueKeys = [...array_values(self::OVERDUE_BUCKETS), self::OVERDUE_OVER];
        $dueKeys = [...array_values(self::DUE_BUCKETS), self::DUE_OVER];

        return [
            'invoiced_total' => $this->money(array_sum(array_map(fn (LedgerEntry $entry): int => $entry->totalCents, $period))),
            'settled_total' => $this->money(array_sum(array_map(fn (LedgerEntry $entry): int => $entry->settledCents(), $period))),
            'residual_total' => $this->money($residualCents),
            'overdue_total' => $this->money(array_sum(array_intersect_key($aging, array_flip($overdueKeys)))),
            'not_due_total' => $this->money(array_sum(array_intersect_key($aging, array_flip($dueKeys)))),
            'aging' => array_map(fn (int $cents): float => $this->money($cents), $aging),
        ];
    }

    /**
     * Cents of the open installments per bucket.
     *
     * @param  array<int, LedgerEntry>  $entries
     * @return array<string, int>
     */
    private function aging(array $entries, CarbonImmutable $today): array
    {
        $buckets = array_fill_keys(
            [...array_values(self::OVERDUE_BUCKETS), self::OVERDUE_OVER, ...array_values(self::DUE_BUCKETS), self::DUE_OVER],
            0,
        );

        foreach ($entries as $entry) {
            foreach ($entry->installments as $row) {
                if ($row->residualCents() > 0) {
                    $buckets[$this->bucket($row->dueDate, $today)] += $row->residualCents();
                }
            }
        }

        return $buckets;
    }

    private function bucket(CarbonImmutable $dueDate, CarbonImmutable $today): string
    {
        if ($dueDate->lt($today)) {
            return $this->bucketOf((int) $dueDate->diffInDays($today, true), self::OVERDUE_BUCKETS, self::OVERDUE_OVER);
        }

        return $this->bucketOf((int) $today->diffInDays($dueDate, true), self::DUE_BUCKETS, self::DUE_OVER);
    }

    /**
     * @param  array<int, string>  $bounds  upper bound (days, inclusive) => bucket
     */
    private function bucketOf(int $days, array $bounds, string $beyond): string
    {
        foreach ($bounds as $limit => $bucket) {
            if ($days <= $limit) {
                return $bucket;
            }
        }

        return $beyond;
    }

    private function money(int $cents): float
    {
        return round($cents / self::CENTS, 2);
    }
}
