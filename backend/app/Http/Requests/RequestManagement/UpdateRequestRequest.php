<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement;

use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesManagerSlots;
use App\Http\Requests\Concerns\ValidatesProductLines;
use App\Http\Requests\Concerns\ValidatesQuoteLines;
use App\Http\Requests\Concerns\ValidatesQuoteWorkflowStatus;
use App\Http\Requests\Concerns\ValidatesRequestClientProfile;
use App\Http\Requests\Concerns\ValidatesRewards;
use App\Models\Quote;
use App\Models\User;
use App\Support\ManagerPositions;
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
    use EnforcesFieldPermissions, ValidatesManagerSlots, ValidatesProductLines, ValidatesQuoteLines, ValidatesQuoteWorkflowStatus, ValidatesRequestClientProfile, ValidatesRewards {
        EnforcesFieldPermissions::currentFieldValue as private traitCurrentFieldValue;
    }

    /** The team field's wire key, gated by the field-permission matrix (spec 0097, D-3). */
    private const string MANAGER_SLOTS_FIELD = 'manager_slots';

    /** The one catalogued field that still lives on the parent Opportunity (spec 0086, D-2). */
    private const string PRODUCT_LINES_FIELD = 'product_lines';

    /**
     * The "the persisted team is NOT what was submitted" marker
     * currentManagerSlots() reports through: a plain string, so no submitted
     * `manager_slots` payload (an array, possibly empty) can compare equal to
     * it once EnforcesFieldPermissions normalizes both sides.
     */
    private const string MANAGER_SLOTS_CHANGED = 'manager_slots:changed';

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
            // "Supervisore" (spec 0097, D-9): the Offerta's commission
            // recipient, back on this channel by user directive 2026-09-02
            // with the SAME rule UpdateQuoteRequest declares for it — one
            // field, one shape, two forms. It is an internal User (unlike
            // the Segnalatore above, a Referent) and it is an independent
            // scalar: it drives no pivot and no assignment (AC-014).
            'supervisor_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
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
            // Spec 0097, D-1/D-3: the panel's attribution block writes the
            // Offerta's WHOLE team, with the same ordered, gap-aware shape
            // and the same shared trait the quotes endpoints use — the lone
            // `operator_id` picker this endpoint used to take is GONE from
            // the rules (it survives ONLY as `updateWork()`'s internal key
            // for the grid cell / bulk assign / transfer channels, which
            // never come through this FormRequest).
            ...$this->managerSlotsRules(),
            // …and that removal is declared, not silent (lead decision
            // 2026-09-02). The two keys address the SAME pivot from two
            // vocabularies — the whole team vs its OPERATOR slot alone — so a
            // payload carrying both would have two writers racing on one
            // collection, and one carrying `operator_id` ALONE would be
            // validated, stripped by the controller and answered 200 with
            // nothing written. That silent 200 is exactly the failure spec
            // 0086 mt06 already paid for in production, so the key is
            // REJECTED here rather than ignored: `prohibited` covers both
            // shapes at once (AC-008), which is why `manager_slots` carries
            // no `prohibits:operator_id` of its own — one rule, one truth.
            //
            // KNOWN LIMIT: Laravel's `prohibited` is `! required`, so it
            // passes on an EMPTY value — a stale client sending
            // `operator_id: null` still gets a 200 no-op. Left as is on
            // purpose: null asks for no write, so nothing is lost, and a
            // custom rule to catch it would cost more comprehension than the
            // case is worth.
            'operator_id' => ['prohibited'],
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
     * relations/attributes declared on $model directly (spec 0008). Two of
     * this endpoint's catalogued fields no longer live on the route-bound
     * Quote (spec 0086, D-2): `product_lines` stayed Opportunity-level
     * (read through the Quote's own `opportunity` relation).
     * `next_callback_at` left that set with the user directive 2026-09-04 —
     * a real Quote column now, read by the generic reader. `source_id` needs
     * no override: Quote's own virtual `sourceId()` accessor (D-10) already
     * reads through correctly.
     *
     * `manager_slots` (spec 0097) needs one for a different reason: it is a
     * WIRE shape, not an attribute — the persisted side is the `quote_user`
     * pivot, which the generic reader cannot reach (it looks for a
     * `managerSlots` relation and finds none, so it reads null and every
     * submission counts as a change, 422-ing an untouched block).
     * UpdateQuoteRequest gates the identical key with no override and thus
     * carries that coarser behaviour; it is out of this spec's scope, so the
     * divergence is deliberate and reported, not silently mirrored.
     */
    protected function currentFieldValue(?Model $model, string $field): mixed
    {
        if ($model instanceof Quote && $field === self::MANAGER_SLOTS_FIELD) {
            return $this->currentManagerSlots($model);
        }

        if ($model instanceof Quote && $field === self::PRODUCT_LINES_FIELD) {
            return $this->traitCurrentFieldValue($model->opportunity, $field);
        }

        return $this->traitCurrentFieldValue($model, $field);
    }

    /**
     * Answers the ONE question EnforcesFieldPermissions asks — "does the
     * submitted value differ from the persisted one?" — for `manager_slots`,
     * and answers it EXACTLY: the trait's own comparison normalizes lists
     * order-insensitively, which would read a pure slot permutation (a move
     * of the OPERATOR slot, i.e. a change of operator!) as a no-op and let it
     * through a locked field.
     *
     * So the two sides are compared here in the pivot vocabulary the write
     * path itself uses (ManagerPositions::syncMap(), userId => position):
     * equal means the write would be a literal no-op, and the SUBMITTED
     * value is handed back so the trait compares it with itself; different
     * means a genuine change, reported through a sentinel no array payload
     * can ever equal — including the empty array, which is a real change
     * (it clears the whole team) and must never compare equal to "nothing".
     */
    private function currentManagerSlots(Quote $quote): mixed
    {
        $submitted = $this->input(self::MANAGER_SLOTS_FIELD);

        $incoming = ManagerPositions::syncMap(is_array($submitted) ? array_values($submitted) : []);
        $persisted = $quote->managers()->get()
            ->mapWithKeys(static fn (User $manager): array => [
                (int) $manager->id => ['position' => (int) $manager->pivot->position],
            ])
            ->all();

        ksort($incoming);
        ksort($persisted);

        return $incoming === $persisted ? $submitted : self::MANAGER_SLOTS_CHANGED;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Quote $quote */
            $quote = $this->route('quote');

            $this->validateProductLines($validator);
            $this->validateManagerSlots($validator);
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
            // Spec 0102, D-3/AC-044/045: the panel's mirror of the writer's
            // own closing/validating-group gate, keyed on `offer_lines` so
            // the panel can attach the 422 to the lines block instead of a
            // generic toast. QuoteWorkflowStatusWriter::apply() (reached via
            // RequestManagementService::applyWorkflowStatus()) stays the
            // actual enforcing gate — this only pre-empts it with the same
            // rejection.
            $this->validateQuoteWorkflowStatusRequiresOfferLine($validator, $quote);
            $this->validateClientProfile($validator);
            // Write-path counterpart of the `permissions` block (spec 0004/
            // 0008): a field the actor's role may not edit is rejected 422
            // when its value actually CHANGES, so the panel's per-field
            // gating is not frontend-only.
            $this->enforceFieldPermissions($validator);
        });
    }
}
