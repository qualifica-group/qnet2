<?php

namespace App\Http\Requests\Projects;

use App\DataObjects\Projects\UpdateProjectData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesGeoHierarchy;
use App\Http\Requests\Concerns\ValidatesProductLines;
use App\Models\Project;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/projects/{project} (spec 0023).
 * Every field is `sometimes` to support partial PATCH updates; `code` is
 * intentionally NOT a rule: it is writable only in create and permanently
 * read-only afterwards (spec 0025, BR-1). An unsubmitted or unchanged `code`
 * is silently dropped by validated(); a CHANGED one is rejected with a 422
 * by EnforcesFieldPermissions below (its ceiling is readonly once $model
 * exists).
 *
 * Spec 0094, D-1/D-2: `business_function_id`/`product_category_id` are
 * REPLACED by `product_lines` (ValidatesProductLines) — a full-replace
 * to-many collection that may be OMITTED (partial PATCH, rows untouched) but
 * may NOT be cleared to `[]` (a project always keeps at least one row).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $project)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific $project.
 */
class UpdateProjectRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'pipeline_status_id' => ['sometimes', 'required', 'integer', Rule::exists('pipeline_statuses', 'id')],
            'description' => ['sometimes', 'nullable', 'string'],
            'country_id' => ['sometimes', 'required', 'integer', Rule::exists('countries', 'id')],
            'state_id' => ['sometimes', 'nullable', 'integer', Rule::exists('states', 'id')],
            'province_id' => ['sometimes', 'nullable', 'integer', Rule::exists('provinces', 'id')],
            'city_id' => ['sometimes', 'nullable', 'integer', Rule::exists('cities', 'id')],
            'partner_id' => ['sometimes', 'nullable', 'integer', Rule::exists('referents', 'id')],
            'operational_site_id' => ['sometimes', 'nullable', 'integer', Rule::exists('operational_sites', 'id')],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'total_budget' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'target_lead' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ], $this->productLinesRules(required: false));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);

            // Only re-validate the hierarchy when THIS request actually
            // touches a geo level: an update that never mentions geo must
            // not fail on a pre-existing row it does not itself change (a
            // legacy/demo project could predate BR-4, D-4).
            $geoFields = ['country_id', 'state_id', 'province_id', 'city_id'];

            if ($this->hasAny($geoFields) && ! $validator->errors()->hasAny($geoFields)) {
                $this->validateGeoHierarchy($validator, $this->effectiveGeo());
            }

            $this->validateProductLines($validator);
        });
    }

    /**
     * The geo tuple resulting from this update: the submitted value for each
     * level, falling back to the project's CURRENT one when a level is not
     * touched by this request — BR-4 is validated against this resulting
     * tuple, not the raw submitted delta.
     *
     * @return array{country_id: int|null, state_id: int|null, province_id: int|null, city_id: int|null}
     */
    private function effectiveGeo(): array
    {
        $project = $this->currentProject();

        return [
            'country_id' => $this->has('country_id') ? (int) $this->input('country_id') : $project->country_id,
            'state_id' => $this->resolvedLevel('state_id', $project->state_id),
            'province_id' => $this->resolvedLevel('province_id', $project->province_id),
            'city_id' => $this->resolvedLevel('city_id', $project->city_id),
        ];
    }

    /**
     * A nullable geo level's resulting value: the submitted one (possibly an
     * explicit null, clearing it) when present in the request, else the
     * project's current value.
     */
    private function resolvedLevel(string $field, ?int $current): ?int
    {
        if (! $this->has($field)) {
            return $current;
        }

        return $this->filled($field) ? (int) $this->input($field) : null;
    }

    protected function authorizationResource(): string
    {
        return 'projects';
    }

    protected function authorizationModel(): ?Model
    {
        return $this->currentProject();
    }

    private function currentProject(): Project
    {
        /** @var Project $project */
        $project = $this->route('project');

        return $project;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateProjectData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateProjectData::fromValidated($validated);
    }
}
