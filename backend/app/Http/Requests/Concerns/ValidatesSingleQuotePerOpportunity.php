<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Opportunity;
use App\Services\Opportunities\OpportunityQuoteLimit;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * User directive 2026-08-07: an opportunity whose product category carries
 * `single_quote_per_opportunity` accepts ONE quote only. Used by
 * StoreQuoteRequest alone — an update never adds a document, so there is
 * nothing to bound there (and grandfathering an opportunity that already has
 * several quotes is deliberate, see OpportunityQuoteLimit).
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesSingleQuotePerOpportunity
{
    protected function enforceSingleQuotePerOpportunity(Validator $validator): void
    {
        $opportunityId = $this->input('opportunity_id');

        if (! is_numeric($opportunityId)) {
            return;
        }

        $opportunity = Opportunity::find((int) $opportunityId);

        // An invalid id is already reported by its own `exists` rule.
        if ($opportunity === null) {
            return;
        }

        if (app(OpportunityQuoteLimit::class)->allowsAdditionalQuote($opportunity)) {
            return;
        }

        $validator->errors()->add('opportunity_id', __(OpportunityQuoteLimit::SINGLE_QUOTE_MESSAGE));
    }
}
