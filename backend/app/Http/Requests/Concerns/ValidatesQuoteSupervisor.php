<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Opportunity;
use App\Models\Quote;
use Illuminate\Contracts\Validation\Validator;

/**
 * Server-side guard for the Supervisore -> Opportunita' dependency on a Quote
 * (user directive 2026-08-06): the submitted `supervisor_id` must be one of
 * the opportunity's Gestori Account (`opportunity_user` pivot).
 *
 * The scoped picker in the form (users/for-select?opportunity_id=...) is an
 * affordance only — security.md §1 "trust nothing": a payload naming a user
 * who does not manage that opportunity must be rejected here, not merely
 * discouraged in the UI. Same shape as ValidatesQuoteCompanySite, shared by
 * Store/UpdateQuoteRequest.
 */
trait ValidatesQuoteSupervisor
{
    /**
     * Reject a `supervisor_id` who is not a Gestore Account of the quote's
     * opportunity. Only ever runs on a SUBMITTED, non-null value: an absent
     * key inherits (QuoteService::applySnapshotDefaults, itself GA-filtered)
     * on create and leaves the persisted value untouched on update, so an
     * offer saved before this rule existed stays editable on its other fields.
     */
    protected function enforceSupervisorIsOpportunityManager(Validator $validator, ?Quote $quote): void
    {
        if (! $this->has('supervisor_id')) {
            return;
        }

        $supervisorId = $this->input('supervisor_id');

        if ($supervisorId === null || $supervisorId === '') {
            return;
        }

        // `opportunity_id` is required on create and prohibited on update
        // (AC-025, immutable), so the target opportunity is the submitted one
        // or the persisted one — never both.
        $opportunityId = $quote?->opportunity_id ?? $this->input('opportunity_id');

        // A missing/non-existent opportunity is already a failure on its own
        // rule; do not stack a second, misleading message on top of it.
        if ($opportunityId === null || $opportunityId === '') {
            return;
        }

        $isManager = Opportunity::query()
            ->whereKey((int) $opportunityId)
            ->whereHas('managers', fn ($managers) => $managers->whereKey((int) $supervisorId))
            ->exists();

        if (! $isManager) {
            $validator->errors()->add('supervisor_id', __('quotes.supervisor_not_manager'));
        }
    }
}
