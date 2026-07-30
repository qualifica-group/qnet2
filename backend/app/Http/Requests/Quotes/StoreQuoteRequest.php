<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotes;

use App\DataObjects\Quotes\CreateQuoteData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesQuoteCompanySite;
use App\Http\Requests\Concerns\ValidatesQuoteLayout;
use App\Http\Requests\Concerns\ValidatesQuoteLineCommissions;
use App\Http\Requests\Concerns\ValidatesQuoteLines;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/quotes (spec 0065). `code` is
 * optional: when absent, null or empty, QuoteService falls back to the
 * sequential QUO-0001 generator (D-13); when submitted, it must be unique
 * against `quotes.code`. `commercial_id`/`reporter_id`/`supervisor_id` are
 * plain nullable relations here — the D-3 "inherit from the opportunity
 * unless submitted" rule is a WRITE-side concern, resolved by QuoteService
 * from whether the key is present in $this->validated(), never here.
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
            'quote_status_id' => ['nullable', 'integer', Rule::exists('quote_statuses', 'id')],
            'commercial_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'reporter_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'company_site_id' => ['nullable', 'integer', Rule::exists('company_sites', 'id')],
            'operational_site_id' => ['nullable', 'integer', Rule::exists('operational_sites', 'id')],
            'layout_id' => ['sometimes', 'nullable', 'integer', Rule::exists('document_layouts', 'id')],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'summary' => ['prohibited'],
        ], $this->quoteLinesRules());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->enforceCommissionFieldPermissions($validator, null);
            $this->enforceCommissionRecipients($validator, null);
            $this->enforceCompanySiteBelongsToCompany($validator, null);
            $this->enforceQuoteLayout($validator, null);
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
