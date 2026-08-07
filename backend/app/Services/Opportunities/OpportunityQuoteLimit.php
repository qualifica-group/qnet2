<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Models\Opportunity;
use App\Models\ProductCategory;

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
     * Whether any product category covered by $opportunity carries the flag.
     * A single query on the denormalised column.
     */
    private function isSingleQuoteBranch(Opportunity $opportunity): bool
    {
        $coveredCategoryIds = $opportunity->productLines()->pluck('product_category_id')->all();

        if ($coveredCategoryIds === []) {
            return false;
        }

        return ProductCategory::query()
            ->whereIn('id', $coveredCategoryIds)
            ->where('single_quote_per_opportunity', true)
            ->exists();
    }
}
