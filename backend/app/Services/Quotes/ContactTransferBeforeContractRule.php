<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Models\Contract;
use App\Models\OpportunityProductLine;
use App\Models\Quote;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Spec 0404, part C (rev. user decision 2026-11-20): an offer of a category
 * flagged `requires_contact_transfer_before_contract` (e.g. "Orientamento
 * specialistico") cannot have its contract VALIDATED until its contact has
 * been transferred to another Sede ("Trasferisci contatto", which raises
 * `quotes.is_transferred`, RequestTransferService). The obligation is checked
 * there and nowhere else: the offer moves to Won freely and its contract is
 * born as usual.
 *
 * Read like ContractEligibility (C-D1): ONE category the opportunity covers
 * carrying the flag is enough. The flag is per-node, so the lookup is a single
 * existence query on the line categories themselves.
 */
final class ContactTransferBeforeContractRule
{
    /** Whether the offer still has to be transferred before its contract is validated. */
    public function owesTransfer(Quote $quote): bool
    {
        return ! $quote->is_transferred && $this->coversFlaggedCategory($quote);
    }

    /**
     * 409 with the reason, read by the validate dialog like any refused action.
     */
    public function assertValidatable(Contract $contract): void
    {
        $quote = $contract->quote()->firstOrFail();

        abort_if($this->owesTransfer($quote), Response::HTTP_CONFLICT, __('quotes.contact_transfer_required_for_contract'));
    }

    private function coversFlaggedCategory(Quote $quote): bool
    {
        return OpportunityProductLine::query()
            ->where('opportunity_id', $quote->opportunity_id)
            ->whereHas(
                'productCategory',
                static fn (Builder $query) => $query->where('requires_contact_transfer_before_contract', true),
            )
            ->exists();
    }
}
