<?php

declare(strict_types=1);

namespace App\Services\Commissions\Accruals;

use App\Enums\CommissionRecipientRole;
use App\Models\CommissionAccrual;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\InvoiceLine;
use App\Models\QuoteLine;
use App\Models\QuoteLineCommission;
use Illuminate\Support\Collection;

/**
 * Loads, in a fixed number of queries, the invoices whose accruals depend on
 * one invoice (the ones sharing a quote line, spec 0212 D-5) and builds their
 * buckets: the current commissions (D-1/D-3) plus the buckets that already
 * have movements (expected 0 when the commission is gone, D-2).
 */
final class AccrualStateLoader
{
    /** SUPPLIER commissions are not agent commissions (D-3). */
    private const array ROLES = [
        CommissionRecipientRole::Commercial,
        CommissionRecipientRole::Reporter,
        CommissionRecipientRole::Supervisor,
    ];

    public function __construct(private readonly CommissionShareCalculator $calculator) {}

    public function load(Invoice $invoice): AccrualState
    {
        // Step 1: the quote lines in play = the ones invoiced by the invoice or already holding its movements
        $ownMovements = CommissionAccrual::query()->where('invoice_id', $invoice->id)->get();
        $lineIds = InvoiceLine::query()->where('invoice_id', $invoice->id)->whereNotNull('quote_line_id')->pluck('quote_line_id')
            ->merge($ownMovements->pluck('quote_line_id')->filter())->unique()->values()->all();

        // Step 2: every invoice line on them, hence the affected invoices. They are locked in ascending id
        // order (no deadlocks) and installments and movements are read with locking reads: two syncs on
        // invoices sharing a quote line serialize here, and under REPEATABLE READ a stale snapshot taken
        // earlier by the caller cannot hide the other sync's movements (duplicate delta).
        $invoiceLines = InvoiceLine::query()->whereIn('quote_line_id', $lineIds)->get(['invoice_id', 'quote_line_id', 'net_amount']);
        $invoices = $this->lockInvoices($invoiceLines->pluck('invoice_id')->push($invoice->id)->unique()->sort()->values()->all());

        // Step 3: buckets of the current commissions, then the ones that only have movements
        $buckets = $this->commissionBuckets($lineIds, $invoiceLines->groupBy('quote_line_id'), $invoices);
        $movements = CommissionAccrual::query()->whereIn('invoice_id', $invoices->keys()->all())
            ->where(fn ($query) => $query->whereIn('quote_line_id', $lineIds)->orWhereNull('quote_line_id'))->lockForUpdate()->get();
        [$buckets, $sums] = $this->mergeMovements($buckets, $movements);

        return new AccrualState($invoices->all(), array_values($buckets), $sums);
    }

    /**
     * @param  array<int, int>  $ids  ascending
     * @return Collection<int, Invoice> keyed by id, ordered by (document_date, id), installments loaded
     */
    private function lockInvoices(array $ids): Collection
    {
        $locked = Invoice::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        $installments = InvoiceInstallment::query()->whereIn('invoice_id', $ids)->lockForUpdate()->get()->groupBy('invoice_id');

        return $locked->each(fn (Invoice $row) => $row->setRelation('installments', $installments->get($row->id, new Collection)))
            ->sortBy([['document_date', 'asc'], ['id', 'asc']])->keyBy('id');
    }

    /**
     * @param  array<int, int>  $lineIds
     * @param  Collection<int, Collection<int, InvoiceLine>>  $invoiceLines  by quote line
     * @param  Collection<int, Invoice>  $invoices  ordered by (document_date, id)
     * @return array<string, AccrualBucket>
     */
    private function commissionBuckets(array $lineIds, Collection $invoiceLines, Collection $invoices): array
    {
        $lineNets = QuoteLine::query()->whereIn('id', $lineIds)->pluck('net_amount', 'id');
        $commissions = QuoteLineCommission::query()->whereIn('quote_line_id', $lineIds)
            ->whereIn('recipient_role', array_column(self::ROLES, 'value'))
            ->whereNotNull('recipient_id')->get();
        $buckets = [];

        foreach ($commissions as $commission) {
            $nets = $this->invoiceNets($invoiceLines->get($commission->quote_line_id, new Collection), $invoices);
            $shares = $this->calculator->shares((string) $commission->calculated_amount, (string) $lineNets->get($commission->quote_line_id, '0'), $nets);

            foreach ($shares as $invoiceId => $share) {
                $bucket = new AccrualBucket($invoiceId, $commission->quote_line_id, $commission->recipient_role, (string) $commission->recipient_type, (int) $commission->recipient_id, $share, $commission->id);
                $buckets[$bucket->bucketKey()] = $bucket;
            }
        }

        return $buckets;
    }

    /**
     * Net invoiced on one quote line per invoice (several lines of an invoice
     * add up), in the cumulative order of D-1.
     *
     * @param  Collection<int, InvoiceLine>  $lines
     * @param  Collection<int, Invoice>  $invoices
     * @return array<int, string>
     */
    private function invoiceNets(Collection $lines, Collection $invoices): array
    {
        $byInvoice = $lines->groupBy('invoice_id')->map(fn (Collection $rows): string => $rows->reduce(
            fn (string $sum, InvoiceLine $row): string => bcadd($sum, (string) $row->net_amount, 2),
            '0.00',
        ));

        return $invoices->keys()->filter(fn (int $id): bool => $byInvoice->has($id))
            ->mapWithKeys(fn (int $id): array => [$id => $byInvoice->get($id)])->all();
    }

    /**
     * @param  array<string, AccrualBucket>  $buckets
     * @param  Collection<int, CommissionAccrual>  $movements
     * @return array{0: array<string, AccrualBucket>, 1: array<string, string>}
     */
    private function mergeMovements(array $buckets, Collection $movements): array
    {
        $sums = [];

        foreach ($movements as $movement) {
            $key = AccrualBucket::key($movement->invoice_id, $movement->quote_line_id, $movement->recipient_role, $movement->recipient_type, $movement->recipient_id);
            $sums[$key] = bcadd($sums[$key] ?? '0.00', (string) $movement->amount, 2);
            $buckets[$key] ??= new AccrualBucket($movement->invoice_id, $movement->quote_line_id, $movement->recipient_role, $movement->recipient_type, $movement->recipient_id, '0.00', null);
        }

        return [$buckets, $sums];
    }
}
