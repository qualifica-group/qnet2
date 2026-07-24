<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement;

use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesRequestClientProfile;
use App\Http\Requests\Concerns\ValidatesRewards;
use App\Http\Requests\Concerns\ValidatesWorkflowStatus;
use App\Models\Opportunity;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /api/request-management/{opportunity} (spec 0049 data_contract):
 * sparse payload, only the submitted keys are ever touched.
 *
 * `authorize()` is a pass-through: the resource authorization
 * (`request-management.update`) AND the D-3 manager-scoping guard
 * (RequestManagementScope) both need the resolved {opportunity} route
 * parameter, so they run in the controller (mirrors OpportunityController's
 * own thin-controller pattern), not here.
 *
 * `opportunity_workflow_status_id` reuses ValidatesWorkflowStatus verbatim
 * (spec 0047): membership is checked against the set resolved for the
 * route's PERSISTED opportunity — this module never submits
 * source_id/state_id/product_lines, so the trait's "submitted overrides,
 * else fall back to $current" behaviour always falls back to the opportunity
 * as-is.
 *
 * `attribute_values` deep validation (per-code applicability/type/required,
 * spec 0049 D-4) is intentionally NOT duplicated here: it runs inside
 * RequestManagementService::updateWork() via AttributeValueValidator, the
 * single place that also resolves the applicable set and merges the map —
 * doing it twice would mean resolving CategoryHierarchy::effectiveAttributes()
 * an extra time for no benefit. Its ValidationException (keyed
 * `attribute_values.<code>`) surfaces as the same 422 shape either way
 * (BaseApiController::handleControllerException).
 *
 * `client_contacts`/`client_address` (spec 0049 amendment) come from
 * ValidatesRequestClientProfile: the client anagraphic block the panel edits
 * inline, written on the Registry's PersonalData card. Same sparse rule as
 * every other key — absent means untouched.
 *
 * `rewards` (spec 0059, AC-023) reuses ValidatesRewards verbatim: identical
 * shape/semantics/error codes to the opportunities payload — the two D-3
 * cross-field guards (non-empty `rewards` needs a reporter; `reporter_id`
 * cannot clear while rewards exist) are checked against THIS route's
 * persisted opportunity.
 */
class UpdateRequestRequest extends FormRequest
{
    use EnforcesFieldPermissions, ValidatesRequestClientProfile, ValidatesRewards, ValidatesWorkflowStatus;

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
            'opportunity_workflow_status_id' => ['sometimes', 'nullable', 'integer', 'exists:opportunity_workflow_statuses,id'],
            // Spec 0054 D-5: the note that accompanies an advance to a
            // `requires_note` working status. Without a rule here it would be
            // stripped by validated()/safe() and updateWork() would reject
            // every such advance as note-less, even with the note filled in.
            // Same bound as StoreNoteRequest's `body`: it becomes one.
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'attribute_values' => ['sometimes', 'array'],
            'next_callback_at' => ['sometimes', 'nullable', 'date'],
            // "Prodotti di interesse": MANDATORY (user directive 2026-07-23),
            // same rule as the opportunities form — sparse like every other key
            // here (absent means untouched), but never clearable to `[]`. A
            // product outside the opportunity's product-line categories is
            // ACCEPTED on purpose — OpportunityProductInterestWriter adds the
            // matching product line, which is exactly what the panel warns
            // about before unlocking the picker.
            'products_of_interest' => ['sometimes', 'array', 'min:1'],
            'products_of_interest.*' => ['integer', 'exists:products,id'],
            // Attribution (user directive 2026-07-22): "Fonte",
            // "Segnalatore" and the GA2 "Operatore". Sparse like every other
            // key — absent means untouched, `null` clears the value.
            'source_id' => ['sometimes', 'nullable', 'integer', 'exists:sources,id'],
            'reporter_id' => ['sometimes', 'nullable', 'integer', 'exists:referents,id'],
            'operator_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            // Spec 0056: the Sede operativa, same attribution block, same
            // sparse rule — absent means untouched, `null` clears it.
            'operational_site_id' => ['sometimes', 'nullable', 'integer', 'exists:operational_sites,id'],
            ...$this->rewardsRules(),
            ...$this->clientProfileRules(),
        ];
    }

    protected function authorizationResource(): string
    {
        return 'request-management';
    }

    protected function authorizationModel(): ?Model
    {
        return $this->route('opportunity');
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Opportunity $opportunity */
            $opportunity = $this->route('opportunity');

            $this->validateWorkflowStatus($validator, $opportunity);
            $this->validateRewards($validator, $opportunity);
            $this->validateClientProfile($validator);
            // Write-path counterpart of the `permissions` block (spec 0004/
            // 0008): a field the actor's role may not edit is rejected 422
            // when its value actually CHANGES, so the panel's per-field
            // gating is not frontend-only.
            $this->enforceFieldPermissions($validator);
        });
    }
}
