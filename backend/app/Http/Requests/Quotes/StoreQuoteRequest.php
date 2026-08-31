<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotes;

use App\DataObjects\Quotes\CreateQuoteData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesQuoteCompanySite;
use App\Http\Requests\Concerns\ValidatesQuoteLayout;
use App\Http\Requests\Concerns\ValidatesQuoteLineCommissions;
use App\Http\Requests\Concerns\ValidatesQuoteLines;
use App\Http\Requests\Concerns\ValidatesQuoteWorkflowStatus;
use App\Http\Requests\Concerns\ValidatesRewards;
use App\Http\Requests\Concerns\ValidatesSingleQuotePerOpportunity;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/quotes (spec 0065; spec 0083 T-04 for
 * `quote_workflow_status_id`/`note`). `code` is optional: when absent, null
 * or empty, QuoteService falls back to the sequential QUO-0001 generator
 * (D-13); when submitted, it must be unique against `quotes.code`.
 * `commercial_id`/`reporter_id`/`supervisor_id` are plain nullable relations
 * here — the D-3 "inherit from the opportunity unless submitted" rule is a
 * WRITE-side concern, resolved by QuoteService from whether the key is
 * present in $this->validated(), never here.
 *
 * `quote_workflow_status_id` is an OPTIONAL override (AC-020/021): omitted
 * lets QuoteWorkflowResolver derive the `open` row of the set resolved for
 * the SUBMITTED `offer_lines`/`opportunity_id`; when submitted, its
 * set-membership is checked in withValidator. `note` (AC-023) accompanies an
 * override whose destination `requires_note`.
 *
 * `net_amount`/`vat_amount`/`total_amount` and the whole `summary` block are
 * `prohibited` (AC-033): server-computed, never client input.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', Quote::class)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null).
 */
class StoreQuoteRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesQuoteCompanySite;
    use ValidatesQuoteLayout;
    use ValidatesQuoteLineCommissions;
    use ValidatesQuoteLines;
    use ValidatesQuoteWorkflowStatus;
    use ValidatesRewards;
    use ValidatesSingleQuotePerOpportunity;

    public function authorize(): bool
    {
        // Authorization handled in the controller via QuotePolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge([
            'code' => ['nullable', 'string', 'max:32', Rule::unique('quotes', 'code')],
            'title' => ['required', 'string', 'max:191'],
            'opportunity_id' => ['required', 'integer', Rule::exists('opportunities', 'id')],
            'quote_workflow_status_id' => ['nullable', 'integer', Rule::exists('quote_workflow_statuses', 'id')],
            // Spec 0083, T-04, AC-023: the note a `requires_note` destination
            // demands. Same bound as StoreNoteRequest's `body`.
            'note' => ['nullable', 'string', 'max:5000'],
            'commercial_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'reporter_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'company_site_id' => ['nullable', 'integer', Rule::exists('company_sites', 'id')],
            'operational_site_id' => ['nullable', 'integer', Rule::exists('operational_sites', 'id')],
            'layout_id' => ['sometimes', 'nullable', 'integer', Rule::exists('document_layouts', 'id')],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            // Spec 0084: the dynamic "Informazioni aggiuntive" map. The
            // per-code deep validation (applicability/type/required) is NOT
            // duplicated here: it runs in QuoteAttributeValueWriter, the
            // single place that also resolves the applicable set (from the
            // submitted offer lines' categories) and merges the map, and
            // surfaces as the same 422 keyed `attribute_values.<code>`.
            'attribute_values' => ['sometimes', 'array'],
            'summary' => ['prohibited'],
        ], $this->quoteLinesRules(), $this->rewardsRules());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->enforceCommissionFieldPermissions($validator, null);
            $this->enforceCommissionRecipients($validator, null);
            $this->enforceCompanySiteBelongsToCompany($validator, null);
            $this->enforceQuoteLayout($validator, null);
            $this->enforceSingleOfferLine($validator, null);
            $this->enforceSingleQuotePerOpportunity($validator);
            $this->validateQuoteWorkflowStatus($validator);
            $this->validateRewards($validator, null);
        });
    }

    protected function authorizationResource(): string
    {
        return 'quotes';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateQuoteData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateQuoteData::fromValidated($validated);
    }
}
