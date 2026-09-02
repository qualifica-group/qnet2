<?php

namespace App\Http\Requests\Campaigns;

use App\DataObjects\Campaigns\CreateCampaignData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesGeoHierarchy;
use App\Http\Requests\Concerns\ValidatesProductLines;
use App\Models\Project;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/campaigns (spec 0023; `code`
 * writable-on-create per spec 0025, BR-1). `code` is optional: when absent,
 * null or empty, the Service falls back to the sequential CMP-0001
 * generator; when submitted, it must be unique against `campaigns.code`.
 *
 * BR-2 (campaign-derivation) is enforced right here, value-level: when
 * `project_id` is submitted and non-null, `pipeline_status_id` and
 * `product_lines` (spec 0094, D-1/D-2 — REPLACING the former
 * `business_function_id`/`product_category_id` scalars, ValidatesProductLines)
 * are `prohibited` — present-with-a-value fails validation (AC-022);
 * otherwise they are `required` (AC-023). `filled()` treats both "key absent"
 * and "key present but null" as NOT linked, matching the data contract's
 * `project_id?: int|null`.
 *
 * `country_id`/`state_id`/`province_id`/`city_id` LEFT that group (spec
 * 0027, D-3) and follow BR-5 instead: each level the linked project already
 * fills is `prohibited` on the campaign; the rest are nullable (`country_id`
 * required only when the project itself has none — legacy rows) and
 * validated by ValidatesGeoHierarchy against the MERGED tuple.
 *
 * spec 0039, D-3: when NOT linked, `pipeline_status_id` went from `required`
 * to `nullable` — an omitted FK falls back to the system_key='new' status in
 * CampaignService::create() (server-side default). Linked campaigns are
 * UNCHANGED (still `prohibited`, BR-2 derivation invariant).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', Campaign::class)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null).
 */
class StoreCampaignRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesGeoHierarchy;
    use ValidatesProductLines;

    /**
     * Memoizes linkedProject() (rules() and withValidator() both need it) so
     * a single request never queries the linked project's geo columns twice.
     */
    private ?Project $linkedProjectCache = null;

    private bool $linkedProjectResolved = false;

    public function authorize(): bool
    {
        // Authorization handled in the controller via CampaignPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $linked = $this->filled('project_id');
        $project = $linked ? $this->linkedProject() : null;

        return array_merge([
            'code' => ['nullable', 'string', 'max:32', Rule::unique('campaigns', 'code')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'partner_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'operational_site_id' => ['nullable', 'integer', Rule::exists('operational_sites', 'id')],
            'pipeline_status_id' => $linked
                ? ['prohibited']
                : ['nullable', 'integer', Rule::exists('pipeline_statuses', 'id')],
            'country_id' => $this->countryIdRules($linked, $project),
            'state_id' => $this->geoLevelRules($linked, $project, 'state_id', 'states'),
            'province_id' => $this->geoLevelRules($linked, $project, 'province_id', 'provinces'),
            'city_id' => $this->geoLevelRules($linked, $project, 'city_id', 'cities'),
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'total_budget' => ['nullable', 'numeric', 'min:0'],
            'target_lead' => ['nullable', 'integer', 'min:0'],
        ], $this->productLinesFieldRules($linked));
    }

    /**
     * `product_lines` (spec 0094, D-1/D-2): `prohibited` when linked (BR-2 —
     * the classification is derived from the project), `required` (min 1)
     * when standalone, mirroring `business_function_id`'s former shape.
     * Per-row rules (ValidatesProductLines) always apply so a submitted-but-
     * prohibited collection also reports its own per-row errors.
     *
     * @return array<string, array<int, mixed>>
     */
    private function productLinesFieldRules(bool $linked): array
    {
        $rules = $this->productLinesRules(required: true);

        if ($linked) {
            $rules['product_lines'] = ['prohibited'];
        }

        return $rules;
    }

    /**
     * The `country_id` rule (BR-5): prohibited when the linked project
     * already has one, else required (standalone, OR linked to a legacy
     * project with no country of its own).
     *
     * @return array<int, mixed>
     */
    private function countryIdRules(bool $linked, ?Project $project): array
    {
        if ($linked && $project?->country_id !== null) {
            return ['prohibited'];
        }

        return ['required', 'integer', Rule::exists('countries', 'id')];
    }

    /**
     * The `state_id`/`province_id`/`city_id` rule (BR-5): prohibited when the
     * linked project already fills that level, else simply nullable (the
     * campaign may refine it).
     *
     * @return array<int, mixed>
     */
    private function geoLevelRules(bool $linked, ?Project $project, string $level, string $existsTable): array
    {
        if ($linked && $project?->{$level} !== null) {
            return ['prohibited'];
        }

        return ['nullable', 'integer', Rule::exists($existsTable, 'id')];
    }

    /**
     * The linked project's geo columns only, loaded once for the BR-5 rule
     * table above and the merged-tuple BR-4 check below.
     */
    private function linkedProject(): ?Project
    {
        if ($this->linkedProjectResolved) {
            return $this->linkedProjectCache;
        }

        $projectId = $this->input('project_id');

        $this->linkedProjectCache = is_numeric($projectId)
            ? Project::query()->select(['id', 'country_id', 'state_id', 'province_id', 'city_id'])->find((int) $projectId)
            : null;
        $this->linkedProjectResolved = true;

        return $this->linkedProjectCache;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);

            $relevantFields = ['project_id', 'country_id', 'state_id', 'province_id', 'city_id'];

            if (! $validator->errors()->hasAny($relevantFields)) {
                $linked = $this->filled('project_id');
                $this->validateGeoHierarchy($validator, $this->mergedGeo($linked, $linked ? $this->linkedProject() : null));
            }

            // `product_lines` cross-row rules (spec 0094): a no-op when the
            // campaign is linked and the collection was not submitted (the
            // `prohibited` rule above already reports one that was).
            $this->validateProductLines($validator);
        });
    }

    /**
     * The EFFECTIVE (merged) geo tuple BR-4 validates against (BR-5): the
     * campaign's own submitted value for a writable level, else the linked
     * project's.
     *
     * @return array{country_id: int|null, state_id: int|null, province_id: int|null, city_id: int|null}
     */
    private function mergedGeo(bool $linked, ?Project $project): array
    {
        $submitted = [
            'country_id' => $this->filled('country_id') ? (int) $this->input('country_id') : null,
            'state_id' => $this->filled('state_id') ? (int) $this->input('state_id') : null,
            'province_id' => $this->filled('province_id') ? (int) $this->input('province_id') : null,
            'city_id' => $this->filled('city_id') ? (int) $this->input('city_id') : null,
        ];

        if (! $linked || $project === null) {
            return $submitted;
        }

        return [
            'country_id' => $submitted['country_id'] ?? $project->country_id,
            'state_id' => $submitted['state_id'] ?? $project->state_id,
            'province_id' => $submitted['province_id'] ?? $project->province_id,
            'city_id' => $submitted['city_id'] ?? $project->city_id,
        ];
    }

    protected function authorizationResource(): string
    {
        return 'campaigns';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateCampaignData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateCampaignData::fromValidated($validated);
    }
}
