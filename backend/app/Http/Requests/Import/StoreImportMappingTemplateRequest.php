<?php

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/imports/{domain}/mapping-templates (spec 0035): only
 * `name` (unique per {domain}) and the source `import_run_id` are accepted
 * from the client — `columns`/`column_mapping`/`dedup_strategy` are NEVER
 * taken from the request body, snapshotted server-side from the resolved run
 * instead (anti-tamper, see ImportMappingTemplateController::store()).
 *
 * `import_run_id` domain match and the run's own `column_mapping` presence
 * are NOT checked here (no `exists:` rule): both require resolving the run
 * first, so a mismatch 404s (never 422/403) — stays in the controller, same
 * convention as ImportController::assertRunMatchesDomain() and
 * ConfigureImportRequest's domain-dependent rules.
 */
class StoreImportMappingTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('import_mapping_templates', 'name')
                    ->where(fn ($query) => $query->where('resource', $this->route('domain'))),
            ],
            'import_run_id' => ['required', 'integer'],
        ];
    }
}
