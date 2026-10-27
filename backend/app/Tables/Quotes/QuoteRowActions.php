<?php

namespace App\Tables\Quotes;

use App\Models\Quote;
use App\Models\User;
use App\Services\RequestManagement\RequestManagementScope;
use Illuminate\Support\Facades\Gate;

/**
 * Allowed action keys for one row of the `quotes` grid, split out of
 * QuotesTableDefinition (file-size split, engineering.md §6). Each key is an
 * affordance only: the endpoint the action calls authorizes for real.
 */
final class QuoteRowActions
{
    /**
     * @return array<int, string>
     */
    public function for(User $actor, Quote $quote): array
    {
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $quote)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('delete', $quote)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $quote)) {
            $allowed[] = 'activity';
        }

        // spec 0070: `.docx` generation is a read, gated the same as `view`.
        if (Gate::forUser($actor)->allows('view', $quote)) {
            $allowed[] = 'generate_document';
        }

        if ($this->allowsNotes($actor, $quote)) {
            $allowed[] = 'notes';
        }

        if ($this->allowsContract($actor, $quote)) {
            $allowed[] = 'contract';
        }

        return $allowed;
    }

    /**
     * The SAME rule RequestManagementNotable::authorizeRead re-checks when the
     * note endpoints are called, applied here to the Offerta's PARENT
     * Opportunity: spec 0085 keeps the note attached to the Opportunity and
     * only scopes it with `quote_id`, so reading an Offerta's notes is
     * reading its Opportunity's thread — never a `quotes.*` permission.
     *
     * Spec 0087, D-10: the gate evaluates the OFFERTA's own GA2 Operatore
     * (`quote.operator_id`, a real column — no eager-load needed), not the
     * parent Opportunity's — the two can diverge since the Offerta acquired
     * its own team (D-1). Mirrors OpportunitiesTableDefinition::allowsNotes'
     * shape, not its source.
     *
     * Spec 0105: the third tier is delegated to RequestManagementScope rather
     * than re-spelled here — `operational_site_id` is a real column on the
     * row, so the check costs no query, and the actor's own Sedi are resolved
     * once per request by the scope's loadMissing.
     */
    private function allowsNotes(User $actor, Quote $quote): bool
    {
        if (! $actor->can('request-management.view')) {
            return false;
        }

        return $actor->can('request-management.viewAll')
            || $quote->operator_id === $actor->id
            || RequestManagementScope::isInActorSites($actor, $quote);
    }

    /**
     * Spec 0315, D-1/D-2: only an Offerta that has a Contract (eager-loaded by
     * QuotesTableDefinition::baseQuery) and only for an actor who may view it.
     */
    private function allowsContract(User $actor, Quote $quote): bool
    {
        return $quote->contract !== null && Gate::forUser($actor)->allows('view', $quote->contract);
    }
}
