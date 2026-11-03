<?php

namespace App\Http\Requests\Import;

use App\Imports\ImportRegistry;
use App\Models\ImportRun;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/imports/{domain}/{importRun}/value-mapping (spec 0348).
 * Both payloads are draft wizard state, so only their shape is checked here:
 * unknown column keys are ignored when the preview is built (only keys of the
 * run's own detected columns are ever read), and global_config semantics are
 * the definition's call.
 */
class ValueMappingPreviewRequest extends FormRequest
{
    /**
     * Gate BEFORE validation: an actor without the domain's import permission
     * gets a 403, never a 422. A run of another domain passes through so the
     * controller's 404 guard answers it.
     */
    public function authorize(): bool
    {
        $run = $this->route('importRun');
        $actor = $this->user();

        if (! $run instanceof ImportRun || $run->resource !== (string) $this->route('domain')) {
            return true;
        }

        $definition = app(ImportRegistry::class)->resolve($run->resource);

        return $actor !== null && $actor->can('update', $run) && $definition->authorizeImport($actor);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'column_mapping' => ['required', 'array'],
            'column_mapping.*' => ['required', 'string'],
            'global_config' => ['sometimes', 'array'],
        ];
    }
}
