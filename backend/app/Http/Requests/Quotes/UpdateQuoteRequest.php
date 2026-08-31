<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotes;

use App\DataObjects\Quotes\UpdateQuoteData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesManagerSlots;
use App\Http\Requests\Concerns\ValidatesQuoteCompanySite;
use App\Http\Requests\Concerns\ValidatesQuoteLayout;
use App\Http\Requests\Concerns\ValidatesQuoteLineCommissions;
use App\Http\Requests\Concerns\ValidatesQuoteLines;
use App\Http\Requests\Concerns\ValidatesQuoteWorkflowStatus;
use App\Http\Requests\Concerns\ValidatesRewards;
use App\Models\Quote;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/quotes/{quote} (spec 0065; spec
 * 0083 T-04 for `quote_workflow_status_id`/`note`). Every field is
 * `sometimes` (partial PATCH). `opportunity_id` is `prohibited` (AC-025:
 * immutable). `code` is intentionally NOT a rule here: it is writable only
 * on create and permanently read-only afterwards (D-13, mirrors
 * UpdateProjectRequest) — an unsubmitted or unchanged `code` is silently
 * dropped by validated(); a CHANGED one is rejected with a 422 by
 * EnforcesFieldPermissions below (its ceiling is readonly once $model
 * exists).
 *
 * `offer_lines`/`cost_lines`, when submitted, full-replace the existing set
 * of that type (D-8); omitting the key leaves it untouched.
 *
 * `quote_workflow_status_id` is an OPTIONAL override (AC-021/022): its
 * set-membership is checked in withValidator, against the RESOLVED
 * (possibly changed by a submitted `offer_lines`) set. `note` (AC-023)
 * accompanies an override whose destination `requires_note`.
 *
 * `manager_slots`/`promote_managers_to_opportunity` (spec 0087, AC-003):
 * identical rules to StoreQuoteRequest — omitted leaves the Offerta's GA
 * untouched, an array (even `[]`) is an authoritative full-replace via
 * `App\Services\Quotes\QuoteManagerWriter::sync()`, which also owns the D-6
 * appartenenza check (see StoreQuoteRequest's own docblock).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $quote)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific $quote.
 */
class UpdateQuoteRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesManagerSlots;
    use ValidatesQuoteCompanySite;
    use ValidatesQuoteLayout;
    use ValidatesQuoteLineCommissions;
    use ValidatesQuoteLines;
    use ValidatesQuoteWorkflowStatus;
    use ValidatesRewards;

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
            'opportunity_id' => ['prohibited'],
            'title' => ['sometimes', 'required', 'string', 'max:191'],
            'quote_workflow_status_id' => ['sometimes', 'nullable', 'integer', Rule::exists('quote_workflow_statuses', 'id')],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'commercial_id' => ['sometimes', 'nullable', 'integer', Rule::exists('referents', 'id')],
            'reporter_id' => ['sometimes', 'nullable', 'integer', Rule::exists('referents', 'id')],
            'supervisor_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'company_id' => ['sometimes', 'nullable', 'integer', Rule::exists('companies', 'id')],
            'company_site_id' => ['sometimes', 'nullable', 'integer', Rule::exists('company_sites', 'id')],
            'operational_site_id' => ['sometimes', 'nullable', 'integer', Rule::exists('operational_sites', 'id')],
            'layout_id' => ['sometimes', 'nullable', 'integer', Rule::exists('document_layouts', 'id')],
            'payment_method_id' => ['sometimes', 'nullable', 'integer', Rule::exists('payment_methods', 'id')],
            'internal_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            // Spec 0084: deep validation intentionally NOT duplicated here —
            // see StoreQuoteRequest's own docblock.
            'attribute_values' => ['sometimes', 'array'],
            'promote_managers_to_opportunity' => ['sometimes', 'boolean'],
            'summary' => ['prohibited'],
        ], $this->managerSlotsRules(), $this->quoteLinesRules(), $this->rewardsRules());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->enforceCommissionFieldPermissions($validator, $this->currentQuote());
            $this->enforceCommissionRecipients($validator, $this->currentQuote());
            $this->enforceCompanySiteBelongsToCompany($validator, $this->currentQuote());
            $this->enforceQuoteLayout($validator, $this->currentQuote());
            $this->enforceSingleOfferLine($validator, $this->currentQuote());
            $this->validateManagerSlots($validator);
            $this->validateQuoteWorkflowStatus($validator, $this->currentQuote());
            $this->validateRewards($validator, $this->currentQuote());
        });
    }

    protected function authorizationResource(): string
    {
        return 'quotes';
    }

    protected function authorizationModel(): ?Model
    {
        return $this->currentQuote();
    }

    private function currentQuote(): Quote
    {
        /** @var Quote $quote */
        $quote = $this->route('quote');

        return $quote;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateQuoteData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateQuoteData::fromValidated($validated);
    }
}
