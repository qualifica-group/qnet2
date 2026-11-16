<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Enums\WorkflowStatusGroup;
use App\Models\OpportunityProductLine;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Services\Contracts\ContractEligibility;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Spec 0404, part C: an offer of a category flagged
 * `requires_contact_transfer_before_contract` (e.g. "Orientamento
 * specialistico") cannot be won into a contract until its contact has been
 * transferred to another Sede ("Trasferisci contatto", which raises
 * `quotes.is_transferred`, RequestTransferService).
 *
 * Read like ContractEligibility (C-D1): ONE category the opportunity covers
 * carrying the flag is enough. The flag is per-node, so the lookup is a single
 * existence query on the line categories themselves.
 *
 * Applies only when a contract would actually be born (C-D2): a `closed_won`
 * destination, a branch that generates contracts, no contract yet (an offer
 * that already had one only reactivates it). `validated` ("Vinto provvisorio")
 * stays free.
 */
final class ContactTransferBeforeContractRule
{
    public function __construct(private readonly ContractEligibility $contractEligibility) {}

    /**
     * @throws ValidationException the move would open a contract on an offer that still needs its contact transfer
     */
    public function assertAllowed(Quote $quote, QuoteWorkflowStatus $targetStatus): void
    {
        if (! $this->requiresTransfer($quote, $targetStatus)) {
            return;
        }

        throw ValidationException::withMessages([
            'quote_workflow_status_id' => [__('quotes.contact_transfer_required_for_contract')],
        ]);
    }

    private function requiresTransfer(Quote $quote, QuoteWorkflowStatus $targetStatus): bool
    {
        if ($targetStatus->group !== WorkflowStatusGroup::ClosedWon || (bool) $quote->is_transferred) {
            return false;
        }

        if ($quote->exists && $quote->contract()->exists()) {
            return false;
        }

        return $this->coversFlaggedCategory($quote) && $this->contractEligibility->allowsContract($quote);
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
