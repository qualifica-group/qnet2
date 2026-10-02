<?php

namespace App\Migrations;

use App\Migrations\Concerns\HasMigrationCustomFields;
use App\Migrations\Support\ExternalApiClient;
use App\Models\MigrationRun;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Shared machinery for every concrete MigrationSource (spec 0013). Mirrors
 * App\Imports\AbstractImportDefinition: a concrete source declares only its
 * key/label/columns, its external endpoint, how to map one record to a
 * preview row, how to read its `id`, and how to import one row
 * (processRow()) — the cross-cutting parts (paginating the external system,
 * the read-only preview shape, per-row transaction isolation, run counters
 * and report) live here once.
 *
 * Assumes the external system speaks OUR API dialect
 * (`{items:[...], pagination:{total,offset,limit,total_pages}}`) — see spec
 * 0013 context. `fetchPage()` is the single seam a source can override for a
 * different external shape without touching the rest of the engine.
 */
abstract class AbstractMigrationSource implements MigrationSource
{
    use HasMigrationCustomFields;

    private const int REPORT_FLUSH_SIZE = 200;

    /** @var list<array{old_id: int|string|null, level: string, message: string}> */
    private array $pendingReport = [];

    public function __construct(protected readonly ExternalApiClient $client) {}

    /**
     * Relative path (under config('migrations.base_url')) of this resource's
     * list endpoint on the external system.
     */
    abstract public function endpoint(): string;

    /**
     * This source's OWN preview column catalogue (native fields only) —
     * `columns()` below appends the entity_type's active custom fields
     * generically (spec 0021 auto-persistence), so a concrete source never
     * declares those itself.
     *
     * @return array<int, array{id: string, label: string, type: string}>
     */
    abstract protected function nativeColumns(): array;

    /**
     * Map one external record to its NATIVE preview cells (keys = column
     * id) — `mapRow()` below appends the custom-field cells generically.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, string|int|bool|null>
     */
    abstract protected function mapNativeRow(array $record): array;

    /**
     * {@inheritDoc}
     *
     * Native columns, then this entity_type's active custom fields appended
     * generically (spec 0021) — an empty custom catalogue is a pure
     * passthrough (the common case for a source before its first field).
     */
    public function columns(): array
    {
        return [...$this->nativeColumns(), ...$this->customColumns()];
    }

    /**
     * The external record's own id (the value `old_id` is set to), or null
     * when the record carries none — a fatal, per-row error (never silently
     * skipped) surfaced via processRow().
     *
     * @param  array<string, mixed>  $record
     */
    abstract protected function externalId(array $record): int|string|null;

    /**
     * Import ONE external record: skip if its `old_id` already exists,
     * otherwise create it via the domain Service, set `old_id`, and apply any
     * relational remap — all inside the caller's per-row transaction
     * (importRow()). Thrown exceptions isolate to this row.
     *
     * @param  array<string, mixed>  $record
     */
    abstract protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome;

    public function preview(MigrationQuery $query): MigrationPage
    {
        $payload = $this->fetchPage($query->page, $query->perPage);
        $records = $this->extractRecords($payload);
        $pagination = $this->extractPagination($payload);

        $rows = array_map(fn (array $record): array => $this->mapRow($record), $records);
        $total = $this->extractTotal($pagination);

        return new MigrationPage(
            rows: $rows,
            page: $query->page,
            perPage: $query->perPage,
            total: $total,
            hasMore: $this->hasMorePages($total, $query->page, $query->perPage, count($records)),
        );
    }

    /**
     * Native cells, then this entity_type's active custom-field cells
     * appended generically (spec 0021) — keyed by the definition's raw key,
     * matching the column `id` `columns()` declares for them.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, string|int|bool|null>
     */
    protected function mapRow(array $record): array
    {
        return [...$this->mapNativeRow($record), ...$this->mapCustomRow($record)];
    }

    public function import(MigrationImportContext $context): void
    {
        try {
            // Step 1: create/skip every row in its own per-row transaction.
            $this->eachRecord(fn (array $record): mixed => $this->importRow($context, $record));

            // Step 2: second pass to relink forward references that only became
            // resolvable once every row of this source exists (default: no-op).
            $this->afterImport($context);
        } finally {
            // Step 3: persist the report entries still buffered, even when the
            // run aborts, so the history shows what happened up to the failure.
            $this->flushReport($context->run);
        }
    }

    /**
     * Page size eachRecord() requests during import (independent of the
     * user-controlled preview `per_page`). Overridable per source:
     * config('migrations.import_batch_size') (default 100) is right for a
     * plain JSON listing, but a source whose payload embeds heavy data per
     * row (e.g. DocumentBundlesSource's base64-encoded file content, spec
     * 0175 D-13) must request far fewer rows per page to keep each response
     * a reasonable size.
     */
    protected function importBatchSize(): int
    {
        return (int) config('migrations.import_batch_size', 100);
    }

    /**
     * Paginate the external listing, invoking $handle for every record. Shared
     * by the import pass and any source's afterImport() relinking pass, so both
     * walk the external contract the same way.
     *
     * @param  callable(array<string, mixed>): mixed  $handle
     */
    protected function eachRecord(callable $handle): void
    {
        $page = 1;
        $perPage = $this->importBatchSize();

        do {
            $payload = $this->fetchPage($page, $perPage);
            $records = $this->extractRecords($payload);

            foreach ($records as $record) {
                $handle($record);
            }

            $total = $this->extractTotal($this->extractPagination($payload));
            $hasMore = $this->hasMorePages($total, $page, $perPage, count($records));
            $page++;
        } while ($hasMore);
    }

    /**
     * Hook: a second pass after every row has been imported, for relational
     * references only resolvable once the whole set exists (e.g. a
     * self-referential parent processed after its child in the same run).
     * Default: nothing to relink.
     */
    protected function afterImport(MigrationImportContext $context): void
    {
        // No forward-reference relinking needed by default.
    }

    /**
     * Canonical example of the response envelope this source's external
     * endpoint (`endpoint()`) is expected to return — the "expected
     * template" surfaced read-only to the super-admin alongside `columns()`
     * (spec 0013). One example record is built from `columns()` with a
     * representative value per declared type, wrapped in the SAME envelope
     * `fetchPage()`/`extractRecords()`/`extractPagination()`/`extractTotal()`
     * actually parse — never guessed, always the real parsed shape.
     *
     * @return array{items: array<int, array<string, int|string|bool>>, pagination: array{total: int, offset: int, limit: int, total_pages: int}}
     */
    public function sampleResponse(): array
    {
        $record = [];

        foreach ($this->columns() as $column) {
            $record[$column['id']] = $this->sampleValue($column['type'], $column['id'], $column['label']);
        }

        return [
            'items' => [$record],
            'pagination' => [
                'total' => 1,
                'offset' => 0,
                'limit' => (int) config('migrations.default_per_page', 50),
                'total_pages' => 1,
            ],
        ];
    }

    /**
     * A representative value per declared column type, for `sampleResponse()`.
     */
    private function sampleValue(string $type, string $id, string $label): int|string|bool
    {
        return match ($type) {
            'number' => 1,
            'boolean' => true,
            'date' => '2026-01-01',
            default => $label !== '' ? $label : $id,
        };
    }

    /**
     * Translates the internal page/per_page into OUR external API dialect
     * (`offset`/`limit`) before calling out.
     *
     * @return array<string, mixed>
     */
    protected function fetchPage(int $page, int $perPage): array
    {
        return $this->client->get($this->endpoint(), [
            'offset' => ($page - 1) * $perPage,
            'limit' => $perPage,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    protected function extractRecords(array $payload): array
    {
        return (array) ($payload['items'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function extractPagination(array $payload): array
    {
        return (array) ($payload['pagination'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $pagination
     */
    protected function extractTotal(array $pagination): ?int
    {
        return array_key_exists('total', $pagination) ? (int) $pagination['total'] : null;
    }

    /**
     * A known total resolves has_more precisely — equivalent to the external
     * dialect's `offset + limit < total` since `offset = (page-1)*perPage`
     * and `limit = perPage`, i.e. `offset + limit === page * perPage`. An
     * absent total (AC-007) falls back to "the page came back full", a
     * reasonable heuristic for an unknown-length external listing.
     */
    protected function hasMorePages(?int $total, int $page, int $perPage, int $countReceived): bool
    {
        if ($total !== null) {
            return ($page * $perPage) < $total;
        }

        return $countReceived >= $perPage;
    }

    /**
     * Idempotence check (spec 0013): a row whose `old_id` already exists on
     * the target table is skipped, never duplicated/updated.
     *
     * @param  class-string<Model>  $targetClass
     */
    protected function existsByOldId(string $targetClass, int|string $externalId): bool
    {
        return $targetClass::query()->where('old_id', $externalId)->exists();
    }

    /**
     * Relational remap (spec 0013): resolve a parent referenced by its
     * EXTERNAL id to the qnet record's own id via `old_id`. Null when the
     * parent has not (yet) been migrated — the caller turns this into a
     * non-fatal warning.
     *
     * @param  class-string<Model>  $parentClass
     */
    protected function resolveOldId(string $parentClass, int|string $externalRef): ?int
    {
        /** @var int|null $id */
        $id = $parentClass::query()->where('old_id', $externalRef)->value('id');

        return $id;
    }

    /**
     * Import one external record in its own transaction, isolating a
     * commit-time failure to this row: increments the run's created/skipped/
     * failed counters, appends any warning/error to its report, never blocks
     * the remaining rows.
     *
     * @param  array<string, mixed>  $record
     */
    private function importRow(MigrationImportContext $context, array $record): void
    {
        $run = $context->run;
        $externalId = $this->externalId($record);

        try {
            $outcome = DB::transaction(function () use ($context, $record): MigrationRowOutcome {
                $outcome = $this->processRow($context, $record);

                if ($outcome->model !== null) {
                    $this->persistCustomFields($outcome->model, $record);
                }

                return $outcome;
            });

            if ($outcome->skipped) {
                $run->increment('skipped_rows');
            } else {
                $run->increment('created_rows');
            }

            foreach ($outcome->warnings as $warning) {
                $this->appendReport($run, $externalId, 'warning', $warning);
            }
        } catch (Throwable $exception) {
            $run->increment('failed_rows');
            $this->appendReport($run, $externalId, 'error', 'Failed to import the record: '.$exception->getMessage());
        }

        $run->increment('total_rows');
    }

    /**
     * Buffer one report entry; the buffer is written in batches of
     * REPORT_FLUSH_SIZE (and once more at the end of import()). Rewriting the
     * whole JSON column on every entry is quadratic, and a source of tens of
     * thousands of rows (spec 0189) produces thousands of warnings.
     */
    protected function appendReport(MigrationRun $run, int|string|null $externalId, string $level, string $message): void
    {
        $this->pendingReport[] = ['old_id' => $externalId, 'level' => $level, 'message' => $message];

        if (count($this->pendingReport) >= self::REPORT_FLUSH_SIZE) {
            $this->flushReport($run);
        }
    }

    private function flushReport(MigrationRun $run): void
    {
        if ($this->pendingReport === []) {
            return;
        }

        $run->update(['report' => [...($run->report ?? []), ...$this->pendingReport]]);
        $this->pendingReport = [];
    }
}
