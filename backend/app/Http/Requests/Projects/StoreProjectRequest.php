<?php

namespace App\Http\Requests\Projects;

use App\DataObjects\Projects\CreateProjectData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesGeoHierarchy;
use App\Http\Requests\Concerns\ValidatesProductLines;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/projects (spec 0023; `code`
 * writable-on-create per spec 0025, BR-1). `code` is optional: when absent,
 * null or empty, the Service falls back to the sequential PRJ-0001
 * generator; when submitted, it must be unique against `projects.code`.
 *
 * `country_id` is REQUIRED (spec 0027, BR-4); `state_id`/`province_id`/
 * `city_id` are optional but must form a consistent geo chain, enforced by
 * ValidatesGeoHierarchy.
 *
 * spec 0039, D-3: `pipeline_status_id` went from `required` to `nullable` —
 * an omitted FK falls back to the system_key='new' status in
 * ProjectService::create() (server-side default).
 *
 * Spec 0094, D-1/D-2: `business_function_id`/`product_category_id` are
 * REPLACED by `product_lines` (ValidatesProductLines, the same collection
 * already in use on the Opportunity, amendment rev.3) — a to-many collection,
 * REQUIRED (at least one row) to create.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', Project::class)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null).
 */
class StoreProjectRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesGeoHierarchy;
    use ValidatesProductLines;

    public function authorize(): bool
    {
        // Authorization handled in the controller via ProjectPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge([
            'code' => ['nullable', 'string', 'max:32', Rule::unique('projects', 'code')],
            'name' => ['required', 'string', 'max:191'],
            'pipeline_status_id' => ['nullable', 'integer', Rule::exists('pipeline_statuses', 'id')],
            'description' => ['nullable', 'string'],
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
            'state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
            'province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'partner_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'operational_site_id' => ['nullable', 'integer', Rule::exists('operational_sites', 'id')],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'total_budget' => ['nullable', 'numeric', 'min:0'],
            'target_lead' => ['nullable', 'integer', 'min:0'],
        ], $this->productLinesRules(required: true));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);

            if (! $validator->errors()->hasAny(['country_id', 'state_id', 'province_id', 'city_id'])) {
                $this->validateGeoHierarchy($validator, [
                    'country_id' => $this->filled('country_id') ? (int) $this->input('country_id') : null,
                    'state_id' => $this->filled('state_id') ? (int) $this->input('state_id') : null,
                    'province_id' => $this->filled('province_id') ? (int) $this->input('province_id') : null,
                    'city_id' => $this->filled('city_id') ? (int) $this->input('city_id') : null,
                ]);
            }

            $this->validateProductLines($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'projects';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateProjectData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateProjectData::fromValidated($validated);
    }
}
