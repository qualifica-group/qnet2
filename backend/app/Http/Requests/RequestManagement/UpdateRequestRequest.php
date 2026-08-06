<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement;

use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesProductLines;
use App\Http\Requests\Concerns\ValidatesRequestClientProfile;
use App\Http\Requests\Concerns\ValidatesRewards;
use App\Models\Opportunity;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /api/request-management/{opportunity} (spec 0049 data_contract):
 * sparse payload, only the submitted keys are ever touched. Spec 0083, D-2:
 * this panel no longer advances any working status of its own — the former
 * workflow-status override field and its accompanying `note` are GONE.
 *
 * `authorize()` is a pass-through: the resource authorization
 * (`request-management.update`) AND the D-3 manager-scoping guard
 * (RequestManagementScope) both need the resolved {opportunity} route
 * parameter, so they run in the controller (mirrors OpportunityController's
 * own thin-controller pattern), not here.
 *
 * `product_lines` (user directive 2026-07-31: funzione aziendale + categoria
 * prodotto editable by the commercials from the panel, not only at creation)
 * reuses ValidatesProductLines VERBATIM, the same rules the create form and
 * the opportunities form already share — `sometimes` (absent = untouched)
 * with `min:1`, so the collection can be replaced but never cleared.
 *
 * Spec 0084, D-1: the former `attribute_values` key (spec 0049 D-4) is
 * REMOVED — a value submitted here now produces no write, the dynamic
 * "Informazioni aggiuntive" section having moved to the Offerta (Quote).
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
    use EnforcesFieldPermissions, ValidatesProductLines, ValidatesRequestClientProfile, ValidatesRewards;

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
            'next_callback_at' => ['sometimes', 'nullable', 'date'],
            // "Prodotti di interesse": MANDATORY (user directive 2026-07-23),
            // same rule as the opportunities form — sparse like every other key
            // here (absent means untouched), but never clearable to `[]`. A
            // product outside the request's product-line categories is REFUSED
            // (user directive 2026-07-31, ProductCategoryCoherence): neither
            // module auto-adds the missing line any more (user directive
            // 2026-08-05). The check needs the persisted collections it is
            // diffed against, so it runs in the service, not here.
            'products_of_interest' => ['sometimes', 'array', 'min:1'],
            'products_of_interest.*' => ['integer', 'exists:products,id'],
            // Attribution (user directive 2026-07-22): "Fonte",
            // "Segnalatore" and the GA2 "Operatore". Sparse like every other
            // key — absent means untouched, `null` clears the value. Fonte is
            // the exception: MANDATORY (user directive 2026-07-29), so it is
            // sparse but never clearable to `null` — same shape as
            // `products_of_interest`' own `min:1`.
            'source_id' => ['sometimes', 'required', 'integer', 'exists:sources,id'],
            'reporter_id' => ['sometimes', 'nullable', 'integer', 'exists:referents,id'],
            'operator_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            // Spec 0056: the Sede operativa, same attribution block, same
            // sparse rule — absent means untouched, `null` clears it.
            'operational_site_id' => ['sometimes', 'nullable', 'integer', 'exists:operational_sites,id'],
            ...$this->rewardsRules(),
            ...$this->clientProfileRules(),
            // Funzione aziendale + categoria prodotto (user directive
            // 2026-07-31): `required: false` = sparse, but `min:1` still bars
            // a clear-to-empty, exactly like the opportunities PATCH.
            ...$this->productLinesRules(required: false),
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

            $this->validateProductLines($validator);
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
