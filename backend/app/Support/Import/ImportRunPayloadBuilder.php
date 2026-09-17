<?php

namespace App\Support\Import;

use App\Enums\ImportDedupMode;
use App\Enums\ImportStatus;
use App\Http\Resources\ImportRunResource;
use App\Imports\ImportDefinition;
use App\Imports\Support\ColumnAnalysis;
use App\Imports\Support\ColumnMapper;
use App\Jobs\ProcessStagedImportJob;
use App\Models\ImportMappingTemplate;
use App\Models\ImportRun;

/**
 * Builds the enriched `import_run` payload for GET /api/imports/{domain}/
 * {importRun} (spec 0033 data_contract): the frozen ImportRunResource shape
 * plus the resolved definition's wizard catalogue (fields/global_fields/
 * dedup_modes) and a LIVE-recomputed `suggested_mapping` — diffable against
 * whatever the actor has since edited into `column_mapping`, exactly like
 * AnalyzeImportJob's initial seed (App\Jobs\AnalyzeImportJob). For a legacy
 * (non-wizard) run these are simply the AbstractImportDefinition defaults
 * (empty/null), harmless additions to the frozen spec-0012 shape.
 *
 * `detected_columns` is re-exposed with its deterministic `key`
 * (ColumnAnalysis::columnKeys() — bare name on first occurrence,
 * "{name}#{index}" on a later duplicate) alongside name/index/duplicate:
 * `column_mapping`/`suggested_mapping` are BOTH keyed by this same `key`
 * (never the bare name), so two identically-named file columns never
 * collapse onto the same mapping entry.
 *
 * `review_fields` (spec 0033 delta D-2026-07-15-placeholder-review-fields)
 * is the definition's FINAL persisted field catalogue: the review grid
 * builds its editable columns from this, never from column_mapping's
 * targets, so an input-only field a recognizer replaces (e.g. leads'
 * `full_name` -> `first_name`/`last_name`) is never itself a grid column.
 *
 * `matching_template` (spec 0035) is the domain's most recent
 * ImportMappingTemplate whose `columns` snapshot EXACTLY matches (same
 * list, same order) this run's detected column keys, or null — computed
 * SERVER-SIDE here, never decided by the client.
 *
 * `progress` (spec 0137) counts the rows the running job already handled,
 * computed on read so neither job writes anything extra: during `staging`
 * the staged rows against the analysed `total_rows`, during `processing` the
 * committed (`persisted_at`) rows against the persistable ones. Null in any
 * other status.
 */
final class ImportRunPayloadBuilder
{
    public function __construct(private readonly ColumnMapper $columnMapper) {}

    /**
     * @return array<string, mixed>
     */
    public function build(ImportDefinition $definition, ImportRun $importRun): array
    {
        $payload = (new ImportRunResource($importRun))->resolve(request());

        $payload['detected_columns'] = $importRun->detected_columns !== null
            ? $this->withColumnKeys($importRun->detected_columns)
            : null;
        $payload['suggested_mapping'] = $importRun->detected_columns !== null
            ? $this->columnMapper->suggest($importRun->detected_columns, $definition->fields())->mapping
            : null;
        $payload['fields'] = $definition->fields();
        $payload['global_fields'] = $definition->globalConfig();
        $payload['review_fields'] = $definition->reviewFields();
        $payload['dedup_modes'] = array_map(
            static fn (ImportDedupMode $mode): string => $mode->value,
            $definition->dedupModes(),
        );
        $payload['matching_template'] = $importRun->detected_columns !== null
            ? $this->matchingTemplate($importRun)
            : null;
        $payload['progress'] = $this->progress($importRun);

        return $payload;
    }

    /**
     * @return array{processed: int, total: int}|null
     */
    private function progress(ImportRun $importRun): ?array
    {
        [$processed, $total] = match ($importRun->status) {
            ImportStatus::Staging => [$importRun->rows()->count(), (int) $importRun->total_rows],
            ImportStatus::Processing => [
                $importRun->rows()->whereIn('status', ProcessStagedImportJob::PERSISTABLE_STATUSES)->whereNotNull('persisted_at')->count(),
                $importRun->rows()->whereIn('status', ProcessStagedImportJob::PERSISTABLE_STATUSES)->count(),
            ],
            default => [0, 0],
        };

        return $total > 0 ? ['processed' => min($processed, $total), 'total' => $total] : null;
    }

    /**
     * @return array{id: int, name: string, column_mapping: array<string, string>, dedup_strategy: ?string}|null
     */
    private function matchingTemplate(ImportRun $importRun): ?array
    {
        $columnKeys = ColumnAnalysis::columnKeys($importRun->detected_columns);

        $template = ImportMappingTemplate::query()
            ->where('resource', $importRun->resource)
            ->latest('id')
            ->get()
            ->first(static fn (ImportMappingTemplate $candidate): bool => $candidate->columns === $columnKeys);

        return $template === null ? null : [
            'id' => $template->id,
            'name' => $template->name,
            'column_mapping' => $template->column_mapping,
            'dedup_strategy' => $template->dedup_strategy,
        ];
    }

    /**
     * @param  array<int, array{name: string, index: int, duplicate: bool}>  $columns
     * @return array<int, array{key: string, name: string, index: int, duplicate: bool}>
     */
    private function withColumnKeys(array $columns): array
    {
        $keys = ColumnAnalysis::columnKeys($columns);

        return array_map(
            static fn (array $column, string $key): array => ['key' => $key, ...$column],
            $columns,
            $keys,
        );
    }
}
