<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement;

use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesProductLines;
use App\Http\Requests\Concerns\ValidatesQuoteLines;
use App\Http\Requests\Concerns\ValidatesQuoteWorkflowStatus;
use App\Http\Requests\Concerns\ValidatesRequestClientProfile;
use App\Http\Requests\Concerns\ValidatesRewards;
use App\Models\Quote;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH /api/request-management/{quote} (spec 0049 data_contract, migrated
 * onto the Quote by spec 0086, D-2): sparse payload, only the submitted keys
 * are ever touched.
 *
 * User directive 2026-08-07: the panel writes two more blocks, both the
 * Offerta's own — `attribute_values` ("Informazioni aggiuntive") and the
 * `quote_workflow_status_id`/`note` pair ("Stato di lavorazione"). The pair
 * is the SAME one the quotes endpoints take, validated by the same shared
 * ValidatesQuoteWorkflowStatus: what spec 0083 D-2 removed was the
 * OPPORTUNITY's working state, which no longer exists — this one belongs to
 * the Offerta the request now IS (spec 0086).
 *
 * `authorize()` is a pass-through: the resource authorization
 * (`request-management.update`) AND the D-3 supervisor-scoping guard
 * (RequestManagementScope) both need the resolved {quote} route parameter,
 * so they run in the controller (mirrors OpportunityController's own
 * thin-controller pattern), not here.
 *
 * `product_lines` (user directive 2026-07-31: funzione aziendale + categoria
 * prodotto editable by the commercials from the panel, not only at creation)
 * reuses ValidatesProductLines VERBATIM, the same rules the create form and
 * the opportunities form already share — `sometimes` (absent = untouched)
 * with `min:1`, so the collection can be replaced but never cleared. It
 * still writes through to the Quote's Opportunity (D-2).
 *
 * Spec 0086, AC-022: `products_of_interest` is NO LONGER accepted by this
 * endpoint. Its replacement, `offer_lines`, is instead WRITABLE since the
 * user directive 2026-08-07 ("un componente dove si inseriscono le linee
 * dell'offerta"): the rows are the Offerta's own REVENUE lines, validated by
 * the SAME ValidatesQuoteLines the quotes endpoints use (offer tab only,
 * `commissions` prohibited — that block stays the Offerte form's competence)
 * and written through QuoteService, so coverage (D-7), aggregates (D-9),
 * the derived opportunity name (spec 0077) and the workflow re-resolution
 * (spec 0083) can never diverge between the two channels.
 *
 * `client_contacts`/`client_address` (spec 0049 amendment) come from
 * ValidatesRequestClientProfile: the client anagraphic block the panel edits
 * inline, written on the Registry's PersonalData card via the Quote's
 * Opportunity. Same sparse rule as every other key — absent means untouched.
 *
 * `rewards` (spec 0059, AC-023; D-4) reuses ValidatesRewards verbatim:
 * identical shape/semantics/error codes to the opportunities payload — the
 * two D-3 cross-field guards (non-empty `rewards` needs a reporter;
 * `reporter_id` cannot clear while rewards exist) are checked against THIS
 * route's persisted Quote, now the reward origin (D-4/D-12).
 */
class UpdateRequestRequest extends FormRequest
{
    use EnforcesFieldPermissions, ValidatesProductLines, ValidatesQuoteLines, ValidatesQuoteWorkflowStatus, ValidatesRequestClientProfile, ValidatesRewards {
        EnforcesFieldPermissions::currentFieldValue as private traitCurrentFieldValue;
    }

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
            // Attribution (user directive 2026-07-22): "Fonte" (Opportunity),
            // "Segnalatore" and the GA2 "Operatore" (Quote, D-2). Sparse like
            // every other key — absent means untouched, `null` clears the
            // value. Fonte is the exception: MANDATORY (user directive
            // 2026-07-29), so it is sparse but never clearable to `null`.
            'source_id' => ['sometimes', 'required', 'integer', 'exists:sources,id'],
            'reporter_id' => ['sometimes', 'nullable', 'integer', 'exists:referents,id'],
            'operator_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            // Spec 0056: the Sede operativa, same attribution block, same
            // sparse rule — absent means untouched, `null` clears it.
            'operational_site_id' => ['sometimes', 'nullable', 'integer', 'exists:operational_sites,id'],
            // "Informazioni aggiuntive" (user directive 2026-08-07): shallow
            // here on purpose. The deep per-code validation (applicability/
            // type/required) runs in QuoteAttributeValueWriter against the
            // applicable set THIS module resolves (RequestAttributeResolver,
            // D-1) — the single place that also merges the map, and the only
            // one that knows the set AFTER a `product_lines` replace in the
            // same payload. Its ValidationException surfaces as the same 422
            // shape, keyed `attribute_values.<code>`.
            'attribute_values' => ['sometimes', 'array'],
            // "Stato di lavorazione" (user directive 2026-08-07): the same
            // pair the quotes endpoints take (spec 0083, T-04). Sparse, and
            // `null` is not a clear — an Offerta always carries a working
            // state. `note` is demanded only by a `requires_note` destination,
            // enforced by QuoteWorkflowStatusWriter (AC-023).
            'quote_workflow_status_id' => ['sometimes', 'nullable', 'integer', Rule::exists('quote_workflow_statuses', 'id')],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            // "Linee dell'offerta" (user directive 2026-08-07): the REVENUE
            // rows, full-replace when submitted (D-8) and untouched when the
            // key is absent — the same convention the quotes PATCH follows.
            ...$this->offerLinesOnlyRules(),
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
        return $this->route('quote');
    }

    /**
     * EnforcesFieldPermissions' generic dot-path reader only understands
     * relations/attributes declared on $model directly (spec 0008). Three of
     * this endpoint's catalogued fields no longer live on the route-bound
     * Quote (spec 0086, D-2): `product_lines`/`next_callback_at` stayed
     * Opportunity-level (read through the Quote's own `opportunity`
     * relation), and `operator_id` addresses the Quote's `supervisor_id`
     * column — a differently-named field, mirroring the pivot-accessor
     * precedent this same trait already documents for the pre-migration
     * `Opportunity::operatorId()`. `source_id` needs no override: Quote's own
     * virtual `sourceId()` accessor (D-10) already reads through correctly.
     */
    protected function currentFieldValue(?Model $model, string $field): mixed
    {
        if ($model instanceof Quote) {
            if (in_array($field, ['product_lines', 'next_callback_at'], true)) {
                return $this->traitCurrentFieldValue($model->opportunity, $field);
            }

            if ($field === 'operator_id') {
                return $model->supervisor_id;
            }
        }

        return $this->traitCurrentFieldValue($model, $field);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Quote $quote */
            $quote = $this->route('quote');

            $this->validateProductLines($validator);
            $this->validateRewards($validator, $quote);
            // Spec 0077 / user directive 2026-08-07: an opportunity managed on
            // a `single` product category carries one offer row. Same shared
            // check the quotes endpoints run.
            $this->enforceSingleOfferLine($validator, $quote);
            // Same set check the quotes endpoints run (spec 0083 AC-021),
            // resolved against the SUBMITTED `offer_lines` when this payload
            // carries them (the trait's own transient quote), else against the
            // Offerta's persisted ones.
            $this->validateQuoteWorkflowStatus($validator, $quote);
            $this->validateClientProfile($validator);
            // Write-path counterpart of the `permissions` block (spec 0004/
            // 0008): a field the actor's role may not edit is rejected 422
            // when its value actually CHANGES, so the panel's per-field
            // gating is not frontend-only.
            $this->enforceFieldPermissions($validator);
        });
    }
}
