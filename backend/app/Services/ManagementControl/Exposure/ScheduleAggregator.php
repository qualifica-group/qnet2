<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Exposure;

use App\DataObjects\ManagementControl\ExposureFilters;
use App\Enums\InvoicePaymentStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * One side of the schedule (spec 0269): the open installments with their aging,
 * the flows of the period, the distribution by payment status and the top
 * registries. The receivable and the payable side differ only in tables and
 * columns, which the subclasses declare; everything is aggregated in SQL, so
 * the number of queries never grows with the documents.
 */
abstract class ScheduleAggregator
{
    /**
     * Installments joined to their document (and to whatever the scope needs).
     */
    abstract protected function installments(): Builder;

    /**
     * The documents table, joined to whatever the scope needs.
     */
    abstract protected function documents(): Builder;

    /**
     * Qualified names the generic queries use: `installments`, `documents`, `fk`,
     * `registry`, `due`, `residual`, `settled_amount`, `settled_at`,
     * `document_date`, `invoiced` (expression), `type`, and one column per
     * dimension (`company`, `company_site`, `operational_site`).
     *
     * @return array<string, string>
     */
    abstract public function columns(): array;

    /**
     * @return array<string, mixed>
     */
    public function side(ExposureFilters $filters, string $today): array
    {
        // Step 1: the open schedule at today, by aging bucket
        $aging = $this->agingCents($filters, $today);
        $overdue = array_sum(array_filter($aging, fn (string $bucket): bool => str_starts_with($bucket, 'overdue_'), ARRAY_FILTER_USE_KEY));
        $open = array_sum($aging);

        // Step 2: the flows of the period
        [$settled, $averageDays] = $this->settled($filters);

        // Step 3: distribution by status and top registries
        return [
            'open_total' => ExposureSupport::euros($open),
            'overdue_total' => ExposureSupport::euros($overdue),
            'not_due_total' => ExposureSupport::euros($open - $overdue),
            'aging' => array_map(ExposureSupport::euros(...), $aging),
            'invoiced_in_period' => ExposureSupport::euros($this->invoiced($filters)),
            'settled_in_period' => ExposureSupport::euros($settled),
            'average_days' => $averageDays,
            'by_status' => $this->byStatus($filters, $today),
            'top' => $this->top($filters, $today),
        ];
    }

    /**
     * Open residual cents by dimension id (0 = no value), at today.
     *
     * @return array<int, int>
     */
    public function openCentsBy(ExposureFilters $filters): array
    {
        $column = $this->columns()[$filters->groupBy];
        $residual = $this->columns()['residual'];

        return $this->scopedInstallments($filters)->where($residual, '>', 0)
            ->groupBy($column)
            ->selectRaw("{$column} AS group_id, SUM({$residual}) AS total")
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->group_id => ExposureSupport::cents($row->total)])
            ->all();
    }

    public function scopedInstallments(ExposureFilters $filters): Builder
    {
        return $this->scope($this->installments(), $filters);
    }

    public function scopedDocuments(ExposureFilters $filters): Builder
    {
        return $this->scope($this->documents(), $filters);
    }

    /** Document type, company and sites; the sites of a receivable document come from its offer. */
    private function scope(Builder $query, ExposureFilters $filters): Builder
    {
        $columns = $this->columns();

        return $query
            ->when($filters->documentType !== ExposureFilters::TYPE_ALL, fn (Builder $q) => $q->where($columns['type'], $filters->documentType))
            ->when($filters->companyIds !== [], fn (Builder $q) => $q->whereIn($columns[ExposureFilters::GROUP_COMPANY], $filters->companyIds))
            ->when($filters->companySiteIds !== [], fn (Builder $q) => $q->whereIn($columns[ExposureFilters::GROUP_COMPANY_SITE], $filters->companySiteIds))
            ->when($filters->operationalSiteIds !== [], fn (Builder $q) => $q->whereIn($columns[ExposureFilters::GROUP_OPERATIONAL_SITE], $filters->operationalSiteIds));
    }

    /** The scoped documents dated within the period. */
    public function documentsInPeriod(ExposureFilters $filters): Builder
    {
        return $this->inPeriod($this->scopedDocuments($filters), $this->columns()['document_date'], $filters);
    }

    /** Restricts the query to the rows whose `$column` date falls in the period. */
    private function inPeriod(Builder $query, string $column, ExposureFilters $filters): Builder
    {
        return $query
            ->when($filters->dateFrom !== null, fn (Builder $q) => $q->where($column, '>=', $filters->dateFrom))
            ->when($filters->dateTo !== null, fn (Builder $q) => $q->where($column, '<=', $filters->dateTo));
    }

    /**
     * @return array<string, int> cents by bucket key, in the contract order
     */
    private function agingCents(ExposureFilters $filters, string $today): array
    {
        [$select, $bindings] = AgingSql::buckets($this->columns()['due'], $this->columns()['residual'], $today);
        $row = $this->scopedInstallments($filters)->selectRaw($select, $bindings)->first();

        $cents = [];
        foreach (array_keys(AgingSql::BUCKETS) as $bucket) {
            $cents[$bucket] = ExposureSupport::cents($row->{AgingSql::BUCKET_PREFIX.$bucket} ?? 0);
        }

        return $cents;
    }

    private function invoiced(ExposureFilters $filters): int
    {
        $columns = $this->columns();
        $row = $this->documentsInPeriod($filters)
            ->selectRaw("COALESCE(SUM({$columns['invoiced']}), 0) AS total")->first();

        return ExposureSupport::cents($row->total);
    }

    /**
     * Cents settled in the period and the average days from the document date
     * to the settlement, weighted by the settled amount (D-6).
     *
     * @return array{0: int, 1: int|null}
     */
    private function settled(ExposureFilters $filters): array
    {
        $columns = $this->columns();
        $amount = $columns['settled_amount'];
        $days = AgingSql::daysBetween($columns['document_date'], $columns['settled_at']);
        $query = $this->scopedInstallments($filters)->where($amount, '>', 0)->whereNotNull($columns['settled_at']);
        $row = $this->inPeriod($query, $columns['settled_at'], $filters)
            ->selectRaw("COALESCE(SUM({$amount}), 0) AS settled, COALESCE(SUM({$amount} * {$days}), 0) AS weighted")->first();

        $settled = (float) $row->settled;

        return [ExposureSupport::cents($settled), $settled > 0 ? (int) round((float) $row->weighted / $settled) : null];
    }

    /**
     * Documents and open amount per payment status, from the oldest open due date (D-5).
     *
     * @return array<string, array{documents: int, open: float}>
     */
    private function byStatus(ExposureFilters $filters, string $today): array
    {
        $columns = $this->columns();
        [$due, $residual] = [$columns['due'], $columns['residual']];
        $perDocument = DB::table($columns['installments'])->groupBy($columns['fk'])->selectRaw(
            "{$columns['fk']} AS document_id, MIN(CASE WHEN {$residual} > 0 THEN {$due} END) AS oldest_due, SUM(CASE WHEN {$residual} > 0 THEN {$residual} ELSE 0 END) AS open_amount",
        );
        [$status, $bindings] = AgingSql::status('schedule.document_id', 'schedule.oldest_due', $today);

        $rows = $this->scopedDocuments($filters)
            ->leftJoinSub($perDocument, 'schedule', 'schedule.document_id', '=', "{$columns['documents']}.id")
            ->selectRaw("{$status} AS payment_status, COUNT(*) AS documents, COALESCE(SUM(schedule.open_amount), 0) AS open_amount", $bindings)
            ->groupBy('payment_status')
            ->get()->keyBy('payment_status');

        $cells = [];
        foreach ([InvoicePaymentStatus::Paid, InvoicePaymentStatus::NotDue, InvoicePaymentStatus::Overdue, InvoicePaymentStatus::SeriouslyOverdue] as $case) {
            $row = $rows->get($case->value);
            $cells[$case->value] = ['documents' => (int) ($row->documents ?? 0), 'open' => ExposureSupport::euros(ExposureSupport::cents($row->open_amount ?? 0))];
        }

        return $cells;
    }

    /**
     * The registries with the highest overdue, then open amount (D-7).
     *
     * @return array<int, array<string, mixed>>
     */
    private function top(ExposureFilters $filters, string $today): array
    {
        $columns = $this->columns();
        [$buckets, $bucketBindings] = AgingSql::buckets($columns['due'], $columns['residual'], $today);
        [$totals, $totalBindings] = AgingSql::totals($columns['due'], $columns['residual'], $today);

        $rows = $this->scopedInstallments($filters)
            ->selectRaw("{$columns['registry']} AS registry_id, {$totals}, {$buckets}", [...$totalBindings, ...$bucketBindings])
            ->groupBy($columns['registry'])
            ->having('open_total', '>', 0)
            ->orderByDesc('overdue_total')->orderByDesc('open_total')->orderBy('registry_id')
            ->limit($filters->top)
            ->get();

        $registries = ExposureSupport::registries($rows->pluck('registry_id')->map(fn (mixed $id): int => (int) $id)->all());

        return $rows->map(fn (object $row): array => [
            'registry' => $registries[(int) $row->registry_id] ?? ['id' => (int) $row->registry_id, 'name' => (string) $row->registry_id],
            'open' => ExposureSupport::euros(ExposureSupport::cents($row->open_total)),
            'overdue' => ExposureSupport::euros(ExposureSupport::cents($row->overdue_total)),
            'worst_bucket' => $this->worstBucket($row),
        ])->all();
    }

    private function worstBucket(object $row): string
    {
        foreach (AgingSql::WORST_FIRST as $bucket) {
            if (ExposureSupport::cents($row->{AgingSql::BUCKET_PREFIX.$bucket}) > 0) {
                return $bucket;
            }
        }

        // Unreachable while the top rows require open_total > 0; the spec's fallback is the farthest due bucket (D-7).
        return 'due_over_60';
    }
}
