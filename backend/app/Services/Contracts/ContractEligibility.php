<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\OpportunityProductLine;
use App\Models\Quote;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * THE RULE (spec 0091, user directive 2026-09-01): a product-category branch
 * flagged `generates_contract = false` is not sold under a contract, so an
 * offer of that branch closing positively opens NO `contracts` row — the deal
 * never appears in the Contratti module.
 *
 * Read from the categories the OPPORTUNITY covers (D-2), the same source
 * OpportunityQuoteLimit interrogates, not from the products of the offer
 * lines: an offer with no lines still resolves the card's coverage. The flag
 * is root-owned but DENORMALISED onto every category row
 * (ContractGenerationInheritance), so this is one existence query — no
 * ancestor walk, no query per product line.
 *
 * MOST RESTRICTIVE WINS (D-3): a single covered category with the flag off is
 * enough to withhold the contract, mirroring how spec 0077 D-10 resolves the
 * management mode of a card whose lines sit on different roots.
 *
 * Only ever consulted when a contract would be CREATED. Turning the flag off
 * never touches a contract that already exists (D-4, spec 0072 D-3): the rule
 * gates the birth of a contract, it never deletes history.
 */
final class ContractEligibility
{
    /**
     * Whether the positive close of $quote may open a contract. True whenever
     * the rule does not apply — i.e. no category covered by the quote's
     * opportunity carries the flag off.
     */
    public function allowsContract(Quote $quote): bool
    {
        return ! OpportunityProductLine::query()
            ->where('opportunity_id', $quote->opportunity_id)
            ->whereHas(
                'productCategory',
                static fn (Builder $query) => $query->where('generates_contract', false),
            )
            ->exists();
    }
}
