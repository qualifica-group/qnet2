<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\Quote;
use App\Models\User;
use App\Services\Quotes\QuoteManagerWriter;
use App\Support\ManagerPositions;

/**
 * Writes an offer's GA2 "Operatore" in ONE operation (spec 0087, D-9): it
 * moves the `quote_user` OPERATOR slot (`App\Support\ManagerPositions::
 * OPERATOR`) onto the given user and lets that same write project
 * `quotes.operator_id` — both delegated to `App\Services\Quotes\
 * QuoteManagerWriter` (D-4), the SOLE writer of an Offerta's Gestori Account,
 * so this class never duplicates its pivot/column coherence obligation
 * (D-3/INV-2) nor its appartenenza rule (D-6).
 *
 * `quotes.supervisor_id` is NEVER touched here (D-13/D-14, INV-5): the
 * request-management module no longer reads, writes or deduces it — that
 * column survives only as the Offerta's own commission-recipient role
 * (`CommissionRecipientRole::Supervisor`).
 *
 * Membership (D-6) is guaranteed by always calling QuoteManagerWriter::sync()
 * with `promoteToOpportunity: true`: an operator who is not yet a Gestore
 * Account of the offer's Opportunita' is APPENDED to its first FREE slot
 * (never overwriting an occupied one, D-13) instead of being rejected —
 * request-management's bulk assign/transfer/inline-edit/work-panel channels
 * have no UI to answer the "promote?" question a 422 would otherwise demand,
 * so the module always opts in on the operator's behalf.
 *
 * Successor to RequestSupervisorWriter (spec 0086/spec 0049): the old class
 * wrote `quotes.supervisor_id` AND the Opportunity's own GA2 pivot slot in
 * lockstep; this one writes the OFFERTA's own team instead, and never
 * touches the Opportunity's GA2 slot directly — QuoteManagerWriter's own
 * promotion step is what may add a NEW row to the Opportunity's pivot, on a
 * free slot, never overwriting its existing GA2.
 */
final class RequestOperatorWriter
{
    public function __construct(private readonly QuoteManagerWriter $managerWriter) {}

    /**
     * Reports the transition into $changed/$old under the wire-facing key
     * `operator_id` (nothing when $quote's operator already equals
     * $operatorId) — the pivot/denormalized column pair is not a fillable
     * attribute, so the automatic model-event log never sees it and the
     * CALLER owns the explicit activity entry for that half of the write.
     *
     * The stale `operator` relation cache is dropped for the SAME reason
     * RequestSupervisorWriter used to drop `supervisor`: a caller scoped to
     * "my rows" (RequestManagementScope) can lose the freshly-written row
     * from ITS OWN re-read once the operator changes to someone else
     * (TableCellUpdateService::update() falls back to the in-memory $quote
     * when the post-write re-fetch comes back empty). That fallback instance
     * must already reflect the new operator, or the response ships the OLD
     * one under a 200.
     *
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    public function apply(Quote $quote, ?int $operatorId, array &$changed, array &$old): void
    {
        $current = $quote->operator_id === null ? null : (int) $quote->operator_id;

        if ($current === $operatorId) {
            return;
        }

        $this->managerWriter->sync($quote, $this->slotsWithOperator($quote, $operatorId), promoteToOpportunity: true);
        $quote->unsetRelation('operator');

        $old['operator_id'] = $current;
        $changed['operator_id'] = $operatorId;
    }

    /**
     * The Offerta's CURRENT manager slots (index+1 = position, the shape
     * QuoteManagerWriter::sync() consumes) with the OPERATOR position moved
     * onto $operatorId: every other slot survives untouched. $operatorId is
     * first dropped from wherever it currently sits — including the OPERATOR
     * slot itself, a no-op in that case — so a user already attached at
     * another position is MOVED rather than duplicated across two slots.
     *
     * @return array<int, int|null>
     */
    private function slotsWithOperator(Quote $quote, ?int $operatorId): array
    {
        $positions = $quote->managers()->get()
            ->reject(fn (User $manager): bool => $operatorId !== null && $manager->id === $operatorId)
            ->mapWithKeys(fn (User $manager): array => [(int) $manager->pivot->position => $manager->id])
            ->all();

        if ($operatorId === null) {
            unset($positions[ManagerPositions::OPERATOR]);
        } else {
            $positions[ManagerPositions::OPERATOR] = $operatorId;
        }

        if ($positions === []) {
            return [];
        }

        $size = max(array_keys($positions));
        $slots = array_fill(0, $size, null);

        foreach ($positions as $position => $userId) {
            $slots[$position - 1] = $userId;
        }

        return $slots;
    }
}
