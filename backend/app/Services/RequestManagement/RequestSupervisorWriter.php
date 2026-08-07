<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\Opportunity;
use App\Models\Quote;

/**
 * Writes an offer's Supervisore in ONE operation (spec 0086, D-3): it sets
 * `quotes.supervisor_id` AND synchronizes the GA2 "Operatore" slot — the
 * single `opportunity_user` row at pivot position
 * `Opportunity::OPERATOR_MANAGER_POSITION` — of the offer's opportunity onto
 * the SAME user. The sync is not an extra: it is what keeps
 * `ValidatesQuoteSupervisor::enforceSupervisorIsOpportunityManager()`
 * satisfied for free (that trait accepts any Gestore Account of the
 * opportunity, any position) WITHOUT that trait being touched or loosened
 * (AC-017).
 *
 * Only the position-2 slot is touched — the other manager positions belong
 * to the opportunities form and must survive a write from this module, so
 * `sync()` (which detaches everything absent from its map) is deliberately
 * not used here. A user already attached at another position is MOVED to the
 * operator slot rather than duplicated: the pivot's identity is
 * (opportunity, user), one person cannot hold two slots.
 *
 * The opportunity's CURRENT position-2 holder — not the quote's previous
 * supervisor — is what gets detached: two offers of the same opportunity can
 * carry different supervisors (AC-006), so the pivot slot is resynced from
 * whatever it holds right now, not assumed to mirror this one quote.
 *
 * Successor to RequestOperatorWriter (spec 0049): the old name described the
 * opportunity-pivot write alone; this class also owns the Quote column,
 * which is the write every caller actually wants (spec 0086, D-2). The two
 * coexist ONLY until MT-04 rewrites RequestManagementService/
 * RequestAssignmentService/RequestTransferService onto Quote — those files
 * are outside this microtask's write surface, and deleting RequestOperatorWriter
 * before they stop referencing it would take down `CustomFieldEntityRegistry`
 * (it eagerly resolves every custom-fieldable TableDefinition, including
 * `request-management`'s, through the container) for the WHOLE app, not just
 * this domain. MT-04 deletes RequestOperatorWriter.php once its callers move
 * to this class.
 */
final class RequestSupervisorWriter
{
    /**
     * Reports the transition into $changed/$old under the wire-facing key
     * `operator_id` (nothing when $quote's supervisor already equals
     * $supervisorId) — the pivot is not a fillable attribute, so the
     * automatic model-event log never sees it and the CALLER owns the
     * explicit activity entry for that half of the write.
     *
     * Both halves of the write drop their stale relation cache on $quote —
     * `supervisor` here, `managers` on the opportunity inside
     * syncOperatorManagerSlot() — for the SAME reason: a caller scoped to
     * "my rows" (RequestManagementScope) can lose the freshly-written row
     * from ITS OWN re-read once the supervisor changes to someone else
     * (TableCellUpdateService::update() falls back to the in-memory $quote
     * when the post-write re-fetch comes back empty). That fallback instance
     * must already reflect the new supervisor, or the response ships the
     * OLD one under a 200. Leaving either relation cached defeats this.
     *
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    public function apply(Quote $quote, ?int $supervisorId, array &$changed, array &$old): void
    {
        $current = $quote->supervisor_id;

        if ($current === $supervisorId) {
            return;
        }

        $quote->update(['supervisor_id' => $supervisorId]);
        $quote->unsetRelation('supervisor');

        $this->syncOperatorManagerSlot($this->resolveOpportunity($quote), $supervisorId);

        $old['operator_id'] = $current;
        $changed['operator_id'] = $supervisorId;
    }

    /**
     * Explicit query when the relation is not already loaded — never a bare
     * lazy-loaded property access, so Model::preventLazyLoading() stays
     * satisfied regardless of what the caller eager-loaded on $quote
     * (mirrors Opportunity::operatorManager()'s own discipline).
     */
    private function resolveOpportunity(Quote $quote): Opportunity
    {
        if ($quote->relationLoaded('opportunity')) {
            return $quote->opportunity;
        }

        return $quote->opportunity()->firstOrFail();
    }

    /**
     * Moves the opportunity's GA2 slot onto $supervisorId — detaching
     * whoever currently holds it (null clears the slot, mirroring the
     * pre-D-3 behaviour of an unset operator) and, when a new supervisor is
     * given, detaching them from any other position first so they end up in
     * exactly one row.
     */
    private function syncOperatorManagerSlot(Opportunity $opportunity, ?int $supervisorId): void
    {
        $currentSlotUserId = $opportunity->operatorManager()?->id;

        if ($currentSlotUserId === $supervisorId) {
            return;
        }

        if ($currentSlotUserId !== null) {
            $opportunity->managers()->detach($currentSlotUserId);
        }

        if ($supervisorId !== null) {
            $opportunity->managers()->detach($supervisorId);
            $opportunity->managers()->attach($supervisorId, ['position' => Opportunity::OPERATOR_MANAGER_POSITION]);
        }

        $opportunity->unsetRelation('managers');
    }
}
