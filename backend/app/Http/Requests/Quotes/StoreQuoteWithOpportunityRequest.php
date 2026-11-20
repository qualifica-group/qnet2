<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotes;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Envelope validation for POST /api/quotes/with-opportunity (spec 0396): the
 * two blocks must be objects, and the keys the server owns are refused. The
 * blocks' own rules are NOT duplicated here — CreateQuoteWithOpportunity runs
 * StoreOpportunityRequest / StoreQuoteRequest on them. Authorization stays in
 * the controller.
 */
class StoreQuoteWithOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'opportunity' => ['required', 'array'],
            'opportunity.lead_id' => ['prohibited'],
            'quote' => ['required', 'array'],
            'quote.opportunity_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function opportunityInput(): array
    {
        return (array) $this->input('opportunity');
    }

    /**
     * @return array<string, mixed>
     */
    public function quoteInput(): array
    {
        return (array) $this->input('quote');
    }
}
