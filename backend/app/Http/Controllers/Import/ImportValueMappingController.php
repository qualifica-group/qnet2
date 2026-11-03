<?php

namespace App\Http\Controllers\Import;

use App\Enums\ImportStatus;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Controllers\Import\Concerns\GuardsImportRequests;
use App\Http\Requests\Import\ValueMappingPreviewRequest;
use App\Imports\ImportRegistry;
use App\Imports\Support\ColumnAnalysis;
use App\Models\ImportRun;
use App\Support\Import\ImportValueMappings;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Value-mapping step of the import wizard (spec 0348): lists the distinct
 * values of the value-mapping columns the actor has mapped so far, with the
 * records each can be matched to. Read-only: nothing is persisted until
 * PUT .../configure.
 */
class ImportValueMappingController extends BaseApiController
{
    use AuthorizesRequests;
    use GuardsImportRequests;

    public function __construct(
        private readonly ImportRegistry $registry,
        private readonly ImportValueMappings $valueMappings,
    ) {}

    /**
     * POST /api/imports/{domain}/{importRun}/value-mapping
     */
    public function preview(ValueMappingPreviewRequest $request, string $domain, ImportRun $importRun): JsonResponse
    {
        try {
            // Step 1: resolve + guard (domain without the step, foreign run -> 404)
            $definition = $this->registry->resolve($domain);

            if ($definition->valueMappingFields() === []) {
                throw (new ModelNotFoundException)->setModel(ImportRun::class, [$importRun->id]);
            }

            $this->assertRunMatchesDomain($importRun, $domain);
            $this->authorize('update', $importRun);
            $this->authorizeImport($definition, $request->user());

            if ($importRun->status !== ImportStatus::Configuring) {
                abort(409, 'The import is not in the mapping step.');
            }

            // Step 2: only mapping keys that are real columns of this run
            $detectedKeys = ColumnAnalysis::columnKeys($importRun->detected_columns ?? []);
            $columnMapping = array_intersect_key(
                $request->validated('column_mapping'),
                array_flip($detectedKeys),
            );

            // Step 3: distinct values -> domain preview
            $mappedColumns = $this->valueMappings->mappedColumns($definition, $importRun, $columnMapping);
            $fields = $definition->valueMappingPreview($mappedColumns, $request->validated('global_config') ?? []);

            return $this->ok(['fields' => $fields]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['importRun' => $importRun->id]);
        }
    }
}
