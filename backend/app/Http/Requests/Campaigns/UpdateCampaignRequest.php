<?php

namespace App\Http\Requests\Campaigns;

use App\DataObjects\Campaigns\UpdateCampaignData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesGeoHierarchy;
use App\Http\Requests\Concerns\ValidatesProductLines;
use App\Models\Campaign;
use App\Models\Project;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/campaigns/{campaign} (spec 0023).
 * Every field is `sometimes`; `code` is intentionally NOT a rule: it is
 * writable only in create and permanently read-only afterwards (spec 0025,
 * BR-1) — a CHANGED value is rejected with a 422 by EnforcesFieldPermissions
 * below.
 *
 * BR-2 (campaign-derivation) on a partial update depends on the EFFECTIVE
 * `project_id` after this request (submitted value, or — when `project_id`
 * is not touched — the campaign's current one):
 *  - effectively linked → `pipeline_status_id` AND `product_lines` (spec
 *    0094, D-1/D-2 — REPLACING the former `business_function_id`/
 *    `product_category_id` scalars, ValidatesProductLines) are `prohibited`
 *    (reject an explicit value, mirroring the store rule);
 *  - effectively standalone because THIS request unlinks it (was linked,
 *    now null) → they are `required`: the previous values are NULL/empty in
 *    DB (BR-2), so fresh ones must be supplied in the same request (spec:
 *    "da linked a standalone le rende obbligatorie");
 *  - effectively standalone and UNCHANGED (already standalone, project_id
 *    not touched) → `sometimes`+`required`: optional to resubmit, but a
 *    submitted value cannot be emptied, mirroring UpdateProjectRequest's
 *    mandatory-field pattern.
 *
 * `country_id`/`state_id`/`province_id`/`city_id` LEFT that group (spec
 * 0027, D-3) and follow BR-5's PER-LEVEL ownership instead: a level the
 * EFFECTIVE linked project fills is `prohibited`; every other level is
 * `sometimes`+`nullable` (its resulting value — submitted, or else the
 * campaign's current one — is validated by ValidatesGeoHierarchy against
 * the MERGED tuple below). `country_id` alone additionally becomes required
 * when unlinking (mirroring BR-2) or when linked to a country-less legacy
 * project.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $campaign)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific $campaign.
 */
class UpdateCampaignRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesGeoHierarchy;
    use ValidatesProductLines;

    /**
     * Memoizes effectiveProject() (rules() and withValidator() both need it)
     * so a single request never queries the linked project's geo columns
     * more than once.
     */
    private ?Project $effectiveProjectCache = null;

    private bool $effectiveProjectResolved = false;

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
        return array_merge([
            'project_id' => ['sometimes', 'nullable', 'integer', Rule::exists('projects', 'id')],
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string'],
            'partner_id' => ['sometimes', 'nullable', 'integer', Rule::exists('referents', 'id')],
            'operational_site_id' => ['sometimes', 'nullable', 'integer', Rule::exists('operational_sites', 'id')],
            'pipeline_status_id' => $this->derivedFieldRules(Rule::exists('pipeline_statuses', 'id')),
            'country_id' => $this->countryIdRules(),
            'state_id' => $this->geoLevelRules('state_id', 'states'),
            'province_id' => $this->geoLevelRules('province_id', 'provinces'),
            'city_id' => $this->geoLevelRules('city_id', 'cities'),
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'total_budget' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'target_lead' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ], $this->productLinesFieldRules());
    }

    /**
     * `product_lines` (spec 0094, D-1/D-2), mirroring `derivedFieldRules()`
     * above (BR-2): `prohibited` once effectively linked, `required` (min 1)
     * the moment THIS request unlinks the campaign (its own collection is
     * empty in DB until now), else `sometimes` (min 1 — a submitted value
     * cannot be emptied). Per-row rules (ValidatesProductLines) always merge
     * in, keyed against the campaign's OWN currently-persisted rows (spec
     * 0074 D-3b, resolved generically by the trait).
     *
     * @return array<string, array<int, mixed>>
     */
    private function productLinesFieldRules(): array
    {
        $required = $this->isUnlinkingFromProject();
        $rules = $this->productLinesRules(required: $required);

        if ($this->isLinkedAfterUpdate()) {
            $rules['product_lines'] = ['prohibited'];
        }

        return $rules;
    }

    /**
     * The validation rule set for a BR-2 classification field derived from
     * the (effective) linked project — `pipeline_status_id`, the one
     * remaining scalar (`product_lines`, spec 0094, follows the SAME
     * prohibited/required/sometimes+required shape via
     * productLinesFieldRules() above, but as a to-many collection it cannot
     * share this plain-FK helper).
     *
     * @param  mixed  $existenceRule  rule proving the referenced row is a valid target
     * @return array<int, mixed>
     */
    private function derivedFieldRules(mixed $existenceRule): array
    {
        return match (true) {
            $this->isLinkedAfterUpdate() => ['prohibited'],
            $this->isUnlinkingFromProject() => ['required', 'integer', $existenceRule],
            default => ['sometimes', 'required', 'integer', $existenceRule],
        };
    }

    /**
     * The `country_id` rule (BR-5): prohibited when the EFFECTIVE project
     * already has one; required when unlinking (mirroring BR-2) or when the
     * effective project has no country of its own (legacy row); otherwise
     * optional to resubmit (a value already sits in DB from a prior valid
     * state).
     *
     * @return array<int, mixed>
     */
    private function countryIdRules(): array
    {
        $project = $this->effectiveProject();

        return match (true) {
            $this->isLinkedAfterUpdate() && $project?->country_id !== null => ['prohibited'],
            $this->isUnlinkingFromProject() => ['required', 'integer', Rule::exists('countries', 'id')],
            default => ['sometimes', 'required', 'integer', Rule::exists('countries', 'id')],
        };
    }

    /**
     * The `state_id`/`province_id`/`city_id` rule (BR-5): prohibited when the
     * EFFECTIVE project already fills that level, else simply nullable (the
     * campaign may refine it, or already carries its own value).
     *
     * @return array<int, mixed>
     */
    private function geoLevelRules(string $level, string $existsTable): array
    {
        $project = $this->effectiveProject();

        if ($this->isLinkedAfterUpdate() && $project?->{$level} !== null) {
            return ['prohibited'];
        }

        return ['sometimes', 'nullable', 'integer', Rule::exists($existsTable, 'id')];
    }

    /**
     * Whether the campaign will be linked to a project once this update is
     * applied: the submitted `project_id` when present, else the campaign's
     * current one.
     */
    private function isLinkedAfterUpdate(): bool
    {
        if ($this->has('project_id')) {
            return $this->filled('project_id');
        }

        return $this->currentCampaign()->project_id !== null;
    }

    /**
     * Whether THIS request is the one unlinking a previously-linked campaign
     * (project_id explicitly cleared): the moment its BR-2 classification —
     * `pipeline_status_id` (NULL) and `product_lines` (empty) in DB until
     * now — needs fresh values.
     */
    private function isUnlinkingFromProject(): bool
    {
        return $this->has('project_id')
            && ! $this->filled('project_id')
            && $this->currentCampaign()->project_id !== null;
    }

    /**
     * The project effective once this update is applied — only its geo
     * columns (BR-5 needs nothing else), memoized since rules() calls this up
     * to 4 times and withValidator() once more.
     */
    private function effectiveProject(): ?Project
    {
        if ($this->effectiveProjectResolved) {
            return $this->effectiveProjectCache;
        }

        $projectId = $this->has('project_id')
            ? ($this->filled('project_id') ? (int) $this->input('project_id') : null)
            : $this->currentCampaign()->project_id;

        $this->effectiveProjectCache = $projectId !== null
            ? Project::query()->select(['id', 'country_id', 'state_id', 'province_id', 'city_id'])->find($projectId)
            : null;
        $this->effectiveProjectResolved = true;

        return $this->effectiveProjectCache;
    }

    private function currentCampaign(): Campaign
    {
        /** @var Campaign $campaign */
        $campaign = $this->route('campaign');

        return $campaign;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);

            $relevantFields = ['project_id', 'country_id', 'state_id', 'province_id', 'city_id'];

            // Only re-validate the hierarchy when THIS request actually
            // touches `project_id` (the merge source changes) or a geo
            // level: an update that touches neither must not fail on a
            // pre-existing row it does not itself change (a legacy/demo
            // campaign could predate BR-4, D-4).
            if ($this->hasAny($relevantFields) && ! $validator->errors()->hasAny($relevantFields)) {
                $this->validateGeoHierarchy($validator, $this->mergedGeo());
            }

            // `product_lines` cross-row rules (spec 0094): a no-op when the
            // campaign is effectively linked and the collection was not
            // submitted (the `prohibited` rule above already reports one
            // that was).
            $this->validateProductLines($validator);
        });
    }

    /**
     * The EFFECTIVE (merged) geo tuple BR-4 validates against (BR-5): for
     * each level, the EFFECTIVE project's value when it owns that level,
     * else this update's RESULTING value (submitted — possibly an explicit
     * null clearing it — or, when untouched, the campaign's current one).
     *
     * @return array{country_id: int|null, state_id: int|null, province_id: int|null, city_id: int|null}
     */
    private function mergedGeo(): array
    {
        $project = $this->effectiveProject();
        $campaign = $this->currentCampaign();

        return [
            'country_id' => $project?->country_id ?? $this->resolvedLevel('country_id', $campaign->country_id),
            'state_id' => $project?->state_id ?? $this->resolvedLevel('state_id', $campaign->state_id),
            'province_id' => $project?->province_id ?? $this->resolvedLevel('province_id', $campaign->province_id),
            'city_id' => $project?->city_id ?? $this->resolvedLevel('city_id', $campaign->city_id),
        ];
    }

    /**
     * A geo level's resulting value for THIS update: the submitted one
     * (possibly an explicit null, clearing it) when present in the request,
     * else the campaign's current value.
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
        return 'campaigns';
    }

    protected function authorizationModel(): ?Model
    {
        return $this->currentCampaign();
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateCampaignData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateCampaignData::fromValidated($validated);
    }
}
