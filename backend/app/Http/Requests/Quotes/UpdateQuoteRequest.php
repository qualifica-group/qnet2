<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotes;

use App\DataObjects\Quotes\UpdateQuoteData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesQuoteLines;
use App\Models\Quote;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/quotes/{quote} (spec 0065). Every
 * field is `sometimes` (partial PATCH). `opportunity_id` is `prohibited`
 * (AC-025: immutable). `code` is intentionally NOT a rule here: it is
 * writable only on create and permanently read-only afterwards (D-13,
 * mirrors UpdateProjectRequest) — an unsubmitted or unchanged `code` is
 * silently dropped by validated(); a CHANGED one is rejected with a 422 by
 * EnforcesFieldPermissions below (its ceiling is readonly once $model
 * exists).
 *
 * `offer_lines`/`cost_lines`, when submitted, full-replace the existing set
 * of that type (D-8); omitting the key leaves it untouched.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $quote)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific $quote.
 */
class UpdateQuoteRequest extends FormRequest
{
    use EnforcesFieldPermissions;
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
            'opportunity_id' => ['prohibited'],
            'title' => ['sometimes', 'required', 'string', 'max:191'],
            'quote_status_id' => ['sometimes', 'nullable', 'integer', Rule::exists('quote_statuses', 'id')],
            'commercial_id' => ['sometimes', 'nullable', 'integer', Rule::exists('referents', 'id')],
            'reporter_id' => ['sometimes', 'nullable', 'integer', Rule::exists('referents', 'id')],
            'supervisor_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'internal_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'summary' => ['prohibited'],
        ], $this->quoteLinesRules());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
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
