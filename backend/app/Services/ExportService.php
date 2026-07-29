<?php

namespace App\Services;

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Exports\ExportValueFormatter;
use App\Exports\ExportWriterFactory;
use App\Jobs\GenerateExportJob;
use App\Models\ExportRun;
use App\Models\User;
use App\Services\Table\TableQueryBuilder;
use App\Tables\Quotes\OpportunityScopedTableDefinition;
use App\Tables\TableDefinition;
use App\Tables\TableRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Business logic for the generic per-table export engine (spec 0014): create
 * the ExportRun + dispatch the async job (start), then stream the frozen grid
 * state into the chosen format via the shared TableQueryBuilder + the
 * pluggable ExportWriter (generate, invoked by GenerateExportJob). Mirrors
 * ImportService's disk-write conventions (private disk, uuid path).
 */
class ExportService
{
    public function __construct(
        private readonly TableRegistry $tableRegistry,
        private readonly TableQueryBuilder $queryBuilder,
        private readonly ExportWriterFactory $writerFactory,
        private readonly ExportValueFormatter $formatter,
    ) {}

    /**
     * @param  array{columns: array<int, array{colId: string, header: string}>, sortModel?: array<int, array<string, mixed>>, filterModel?: array<string, array<string, mixed>>, search?: string|null, opportunityId?: int|null}  $state
     */
    public function start(User $actor, TableDefinition $definition, array $state, ExportFormat $format): ExportRun
    {
        // Step 1: build the client-facing filename from the domain + today's date.
        $filename = "{$definition->resource()}-".now()->format('Y-m-d').'.'.$format->extension();

        // Step 2: persist the run (frozen state) as `processing`.
        /** @var ExportRun $run */
        $run = DB::transaction(fn (): ExportRun => ExportRun::create([
            'resource' => $definition->resource(),
            'user_id' => $actor->id,
            'status' => ExportStatus::Processing,
            'format' => $format,
            'original_filename' => $filename,
            'state' => $state,
        ]));

        // Step 3: dispatch the async generation job.
        GenerateExportJob::dispatch($run->id);

        return $run;
    }

    /**
     * Invoked by GenerateExportJob: resolve the definition + actor, build the
     * exact filtered/sorted query the grid would show, stream it in constant
     * memory into the chosen writer, then persist the result.
     */
    public function generate(ExportRun $run): void
    {
        // Step 1: resolve the definition + the actor that started the run.
        $definition = $this->tableRegistry->resolve($run->resource);
        /** @var User $actor */
        $actor = User::query()->findOrFail($run->user_id);

        /** @var array{columns: array<int, array{colId: string, header: string}>, sortModel?: array<int, array<string, mixed>>, filterModel?: array<string, array<string, mixed>>, search?: string|null, opportunityId?: int|null} $state */
        $state = $run->state;
        $columns = $state['columns'];

        // Step 2: re-apply the row scope frozen at request time (spec 0067,
        // D-5) — the state, not the request, is the only thing that survives
        // the async hop into this job, so the setter is invoked here, never
        // in the controller.
        if ($definition instanceof OpportunityScopedTableDefinition && ($state['opportunityId'] ?? null) !== null) {
            $definition->scopeToOpportunity($state['opportunityId']);
        }

        // Step 3: build the query exactly as the grid would (allow-listed
        // filter/search/sort — shared with the interactive TableService).
        $query = $this->queryBuilder->build($definition, $state);

        // Step 4: open the writer on a private, non-guessable path. The
        // writer implementations write straight to the filesystem (never
        // through Storage::put()), so the destination directory must exist
        // up front.
        $disk = Storage::disk((string) config('exports.disk'));
        $directory = (string) config('exports.directory');
        $disk->makeDirectory($directory);

        $writer = $this->writerFactory->make($run->format);
        $path = $directory.'/'.Str::uuid().'.'.$run->format->extension();
        $writer->open($disk->path($path));

        // Step 5: header row, from the frozen state's client-resolved labels.
        $writer->writeHeaders(array_map(static fn (array $column): string => $column['header'], $columns));

        // Step 6: stream the query (constant memory), capped at max_rows —
        // breaking the loop stops the lazy cursor from fetching further pages.
        $columnTypes = $this->columnTypesById($definition);
        $maxRows = (int) config('exports.max_rows');
        $rowCount = 0;

        foreach ($query->lazy((int) config('exports.chunk_size')) as $model) {
            if ($rowCount >= $maxRows) {
                break;
            }

            /** @var Model $model */
            $mapped = $definition->mapRow($actor, $model);
            $writer->writeRow($this->formatRow($mapped, $columns, $columnTypes));
            $rowCount++;
        }

        // Step 7: close + persist the result.
        $writer->close();

        $run->update([
            'file_path' => $path,
            'row_count' => $rowCount,
            'status' => ExportStatus::Completed,
        ]);
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<int, array{colId: string, header: string}>  $columns
     * @param  array<string, string>  $columnTypes
     * @return array<int, string>
     */
    private function formatRow(array $mapped, array $columns, array $columnTypes): array
    {
        return array_map(
            fn (array $column): string => $this->formatter->format(
                $mapped[$column['colId']] ?? null,
                $columnTypes[$column['colId']] ?? 'text',
            ),
            $columns,
        );
    }

    /**
     * @return array<string, string>
     */
    private function columnTypesById(TableDefinition $definition): array
    {
        $types = [];

        foreach ($definition->columns() as $column) {
            $types[$column['id']] = $column['type'];
        }

        return $types;
    }
}
