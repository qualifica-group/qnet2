<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Models\Opportunity;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * THE RULE (user directive 2026-08-07): a product-category branch flagged
 * `single_quote_per_opportunity` accepts AT MOST ONE quote per opportunity.
 *
 * The flag is root-owned but DENORMALISED onto every category row
 * (SingleQuotePerOpportunityInheritance), so the check is a single existence
 * query against the categories the opportunity already covers — no ancestor
 * walk, no query per product line.
 *
 * Distinct from OpportunityProductLineCoverage's `management_mode` rules,
 * which bound how many product-category LINES a card and how many product
 * ROWS an offer may carry: this one bounds the number of QUOTE DOCUMENTS.
 *
 * Only ever consulted when a quote is CREATED. An opportunity that already
 * carries several quotes when the flag is turned on keeps them and stays
 * editable — grandfathering, the same asymmetry the offer-line rule applies
 * (D-5): the rule gates new documents, it never invalidates history.
 */
final class OpportunityQuoteLimit
{
    /**
     * Kept as the ENGLISH source string `__()` is keyed by, like every other
     * message of this family (OpportunityProductLineCoverage::
     * SINGLE_OFFER_LINE_MESSAGE).
     */
    public const string SINGLE_QUOTE_MESSAGE = 'The product category of this opportunity allows a single offer: one already exists.';

    /**
     * Whether $opportunity may take ANOTHER quote. True whenever the rule
     * does not apply (no covered category carries the flag) or no quote
     * exists yet.
     */
    public function allowsAdditionalQuote(Opportunity $opportunity): bool
    {
        if (! $this->isSingleQuoteBranch($opportunity)) {
            return true;
        }

        return ! $opportunity->quotes()->exists();
    }

    /**
     * Whether any product category covered by $opportunity carries the flag —
     * i.e. whether the rule applies AT ALL, regardless of how many quotes
     * already exist. Public because the read side needs it on its own:
     * OpportunityResource ships it so the Offerte panel can disable its
     * "Crea Offerta" affordance instead of letting the operator fill a form
     * that can only 422 (the UI hides, this service still authorizes).
     *
     * One query on the denormalised column, never an ancestor walk: the flag
     * is mirrored onto every category row by
     * SingleQuotePerOpportunityInheritance.
     */
    public function isSingleQuoteBranch(Opportunity $opportunity): bool
    {
        return $opportunity->productLines()
            ->whereHas(
                'productCategory',
                static fn (Builder $query) => $query->where('single_quote_per_opportunity', true),
            )
            ->exists();
    }
}
