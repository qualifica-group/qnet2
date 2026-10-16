<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Billing;

use App\DataObjects\ManagementControl\BillingFilters;
use App\Enums\InvoiceType;
use App\Enums\ProformaRequestKind;
use App\Models\Invoice;
use App\Services\ManagementControl\Exposure\AgingSql;
use App\Services\ManagementControl\Exposure\ExposureSupport;
use App\Services\ManagementControl\FlowSupport;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The life cycle of the active proforma (spec 0271, D-4/D-5): issued in the
 * period (every document is born proforma), converted in the period (date of
 * the first change of `type` to invoice, read from the activity log) and still
 * open today with their age. Amounts are the document total in integer cents;
 * a constant number of aggregate queries, grouped by the kind of the proforma
 * request the document comes from (null when it comes from none).
 */
final class ProformaCycleAggregator
{
    /** Age bucket => last day included, counted from the creation of the proforma; null = unbounded. */
    private const array AGE_BUCKETS = ['0_30' => 30, '31_60' => 60, '61_90' => 90, 'over_90' => null];

    private const string NO_KIND = 'none';

    private const array SCOPE_COLUMNS = [
        'company' => 'invoices.company_id',
        'company_site' => 'quotes.company_site_id',
        'operational_site' => 'quotes.operational_site_id',
    ];

    /**
     * @return array<string, mixed>
     */
    public function handle(BillingFilters $filters, string $today): array
    {
        // Step 1: the documents of each stage, by request kind
        $issued = $this->issued($filters);
        $converted = $this->converted($filters);
        $open = $this->open($filters, $today);
        $openByKind = $this->collapse($open, 'kind');

        // Step 2: the totals, the open ones by age and the kind rows
        return [
            'issued' => $this->summary($issued),
            'converted' => [...$this->summary($converted), 'average_days_to_invoice' => $this->average(array_sum(array_column($converted, 'days')), array_sum(array_column($converted, 'documents')))],
            'open' => $this->summary($openByKind),
            'open_by_age' => $this->openByAge($open),
            'average_days_invoice_to_collection' => $this->daysToCollection($filters),
            'by_kind' => $this->kindRows($issued, $converted, $openByKind),
        ];
    }

    /**
     * Documents created in the period, by request kind.
     *
     * @return array<string, array{documents: int, cents: int, days: int}>
     */
    private function issued(BillingFilters $filters): array
    {
        $query = $this->inPeriod($this->documents($filters), 'invoices.created_at', $filters)
            ->selectRaw('proforma_requests.kind AS request_kind, COUNT(*) AS documents, SUM(invoices.total_amount) AS total, 0 AS days_sum')
            ->groupBy('request_kind');

        return $this->byKind($query->get());
    }

    /**
     * Documents whose first conversion into an invoice falls in the period, by request kind;
     * `days` is the sum of the days between creation and conversion.
     *
     * @return array<string, array{documents: int, cents: int, days: int}>
     */
    private function converted(BillingFilters $filters): array
    {
        $days = AgingSql::daysBetween('DATE(invoices.created_at)', 'DATE(conversion.converted_at)');
        $query = $this->inPeriod($this->documents($filters)->joinSub($this->conversions(), 'conversion', 'conversion.subject_id', '=', 'invoices.id'), 'conversion.converted_at', $filters)
            ->selectRaw("proforma_requests.kind AS request_kind, COUNT(*) AS documents, SUM(invoices.total_amount) AS total, SUM({$days}) AS days_sum")
            ->groupBy('request_kind');

        return $this->byKind($query->get());
    }

    /**
     * The proforma still open at today, by request kind and age bucket.
     *
     * @return array<int, array{kind: string, bucket: string, documents: int, cents: int}>
     */
    private function open(BillingFilters $filters, string $today): array
    {
        $days = AgingSql::daysLate('DATE(invoices.created_at)');
        $cases = [];
        foreach (self::AGE_BUCKETS as $bucket => $limit) {
            $cases[] = $limit === null ? "ELSE '{$bucket}'" : "WHEN {$days} <= {$limit} THEN '{$bucket}'";
        }

        $rows = $this->documents($filters)
            ->where('invoices.type', InvoiceType::Proforma->value)
            ->selectRaw(
                'proforma_requests.kind AS request_kind, CASE '.implode(' ', $cases).' END AS age_bucket, COUNT(*) AS documents, SUM(invoices.total_amount) AS total',
                array_fill(0, count(self::AGE_BUCKETS) - 1, $today),
            )
            ->groupBy('request_kind', 'age_bucket')
            ->get();

        return $rows->map(fn (object $row): array => [
            'kind' => $this->kindKey($row->request_kind),
            'bucket' => (string) $row->age_bucket,
            'documents' => (int) $row->documents,
            'cents' => ExposureSupport::cents($row->total),
        ])->all();
    }

    /**
     * Mean days from the conversion to the collection of the installments collected
     * in the period, weighted by the collected amount; null without data (D-5).
     */
    private function daysToCollection(BillingFilters $filters): ?int
    {
        $days = AgingSql::daysBetween('DATE(conversion.converted_at)', 'invoice_installments.collected_at');
        $row = $this->documents($filters)
            ->joinSub($this->conversions(), 'conversion', 'conversion.subject_id', '=', 'invoices.id')
            ->join('invoice_installments', 'invoice_installments.invoice_id', '=', 'invoices.id')
            ->where('invoice_installments.collected_amount', '>', 0)
            ->when($filters->dateFrom !== null, fn (Builder $q) => $q->where('invoice_installments.collected_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo !== null, fn (Builder $q) => $q->where('invoice_installments.collected_at', '<', $filters->dateToExclusive()))
            ->whereNotNull('invoice_installments.collected_at')
            ->selectRaw("SUM({$days} * invoice_installments.collected_amount) AS weighted, SUM(invoice_installments.collected_amount) AS weight")
            ->first();

        return $this->average((float) $row->weighted, (float) $row->weight);
    }

    /**
     * The invoicing documents with the sites of their offer and the kind of their request, restricted by the scope.
     */
    private function documents(BillingFilters $filters): Builder
    {
        $query = DB::table('invoices')
            ->leftJoin('quotes', 'quotes.id', '=', 'invoices.quote_id')
            ->leftJoin('proforma_requests', 'proforma_requests.id', '=', 'invoices.proforma_request_id');

        return FlowSupport::scope($query, $filters->companyIds, $filters->companySiteIds, $filters->operationalSiteIds, self::SCOPE_COLUMNS);
    }

    /** First conversion into an invoice of each document: the first `type` change to invoice in the log (constant text, bound values). */
    private function conversions(): Builder
    {
        return DB::table('activity_log')
            ->where('subject_type', (new Invoice)->getMorphClass())
            ->where('event', 'updated')
            ->where('properties->attributes->type', InvoiceType::Invoice->value)
            ->groupBy('subject_id')
            ->selectRaw('subject_id, MIN(created_at) AS converted_at');
    }

    /** Timestamp column inside the period: the first day inclusive, the last one up to its end. */
    private function inPeriod(Builder $query, string $column, BillingFilters $filters): Builder
    {
        return $query
            ->when($filters->dateFrom !== null, fn (Builder $q) => $q->where($column, '>=', $filters->dateFrom))
            ->when($filters->dateTo !== null, fn (Builder $q) => $q->where($column, '<', $filters->dateToExclusive()));
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, array{documents: int, cents: int, days: int}>
     */
    private function byKind(Collection $rows): array
    {
        $byKind = [];
        foreach ($rows as $row) {
            $byKind[$this->kindKey($row->request_kind)] = [
                'documents' => (int) $row->documents,
                'cents' => ExposureSupport::cents($row->total),
                'days' => (int) $row->days_sum,
            ];
        }

        return $byKind;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array{documents: int, cents: int}>
     */
    private function collapse(array $rows, string $by): array
    {
        $collapsed = [];
        foreach ($rows as $row) {
            $key = (string) $row[$by];
            $collapsed[$key] = [
                'documents' => ($collapsed[$key]['documents'] ?? 0) + $row['documents'],
                'cents' => ($collapsed[$key]['cents'] ?? 0) + $row['cents'],
            ];
        }

        return $collapsed;
    }

    /**
     * @param  array<string, array{documents: int, cents: int}>  $byKind
     * @return array{documents: int, amount: float}
     */
    private function summary(array $byKind): array
    {
        return [
            'documents' => (int) array_sum(array_column($byKind, 'documents')),
            'amount' => ExposureSupport::euros((int) array_sum(array_column($byKind, 'cents'))),
        ];
    }

    /**
     * @param  array<int, array{kind: string, bucket: string, documents: int, cents: int}>  $open
     * @return array<int, array{bucket: string, documents: int, amount: float}>
     */
    private function openByAge(array $open): array
    {
        $byBucket = $this->collapse($open, 'bucket');

        return array_map(fn (string $bucket): array => [
            'bucket' => $bucket,
            ...$this->summary([$byBucket[$bucket] ?? ['documents' => 0, 'cents' => 0]]),
        ], array_keys(self::AGE_BUCKETS));
    }

    /**
     * Always one row per kind: consultancy, institution and no request.
     *
     * @param  array<string, array{documents: int, cents: int, days: int}>  $issued
     * @param  array<string, array{documents: int, cents: int, days: int}>  $converted
     * @param  array<string, array{documents: int, cents: int}>  $open
     * @return array<int, array<string, mixed>>
     */
    private function kindRows(array $issued, array $converted, array $open): array
    {
        $kinds = [...array_map(fn (ProformaRequestKind $kind): string => $kind->value, ProformaRequestKind::cases()), self::NO_KIND];

        return array_map(fn (string $key): array => [
            'kind' => $key === self::NO_KIND ? null : $key,
            'issued' => $issued[$key]['documents'] ?? 0,
            'converted' => $converted[$key]['documents'] ?? 0,
            'open' => $open[$key]['documents'] ?? 0,
            'average_days_to_invoice' => $this->average($converted[$key]['days'] ?? 0, $converted[$key]['documents'] ?? 0),
        ], $kinds);
    }

    private function kindKey(mixed $kind): string
    {
        return $kind === null ? self::NO_KIND : (string) $kind;
    }

    private function average(float|int $sum, float|int $weight): ?int
    {
        return $weight > 0 ? (int) round($sum / $weight) : null;
    }
}
