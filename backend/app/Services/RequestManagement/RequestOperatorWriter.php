<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\Quote;
use App\Models\User;
use App\Services\Quotes\QuoteManagerWriter;
use App\Support\ManagerPositions;

/**
 * Writes an offer's Gestori Account from the request-management module —
 * apply() moves the GA2 "Operatore" slot alone (the grid cell, the bulk
 * assign, the transfer), applySlots() replaces the WHOLE team (the work
 * panel, spec 0097). Both funnel into the same single writer, so the two
 * channels can never grow divergent rules.
 *
 * apply() (spec 0087, D-9) moves the `quote_user` OPERATOR slot
 * (`App\Support\ManagerPositions::OPERATOR`) onto the given user and lets
 * that same write project
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
     * The WHOLE team in one operation (spec 0097, D-5): the work panel edits
     * every slot at once, so the submitted list replaces the Offerta's
     * `quote_user` wholesale — through the SAME QuoteManagerWriter, with the
     * same `promoteToOpportunity: true` this module always opts into (see the
     * class docblock: no UI here can answer a 422 about appartenenza).
     *
     * Two DIFFERENT keys are reported into $changed/$old (D-6):
     *  - `manager_slots` whenever the team genuinely moves, so the module's
     *    operational history records the new arrangement;
     *  - `operator_id` ONLY when the OPERATOR slot itself changes hands — it
     *    is what the caller's assignment notification hangs off, and
     *    reshuffling the other slots assigns nobody (AC-007).
     * A submission that maps to the persisted arrangement writes nothing at
     * all, exactly like apply()'s own no-op guard.
     *
     * @param  array<int, int|null>  $slots  ordered and gap-aware (index+1 = position), the shape QuoteManagerWriter::sync() consumes
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    public function applySlots(Quote $quote, array $slots, array &$changed, array &$old): void
    {
        $current = $this->currentPositions($quote);
        $submitted = $this->submittedPositions($slots);

        ksort($current);
        ksort($submitted);

        if ($current === $submitted) {
            return;
        }

        $this->managerWriter->sync($quote, $slots, promoteToOpportunity: true);
        $quote->unsetRelation('operator');

        $old['manager_slots'] = $this->positionsToSlots($current);
        $changed['manager_slots'] = $this->positionsToSlots($submitted);

        $previousOperatorId = $current[ManagerPositions::OPERATOR] ?? null;
        $newOperatorId = $submitted[ManagerPositions::OPERATOR] ?? null;

        if ($previousOperatorId === $newOperatorId) {
            return;
        }

        $old['operator_id'] = $previousOperatorId;
        $changed['operator_id'] = $newOperatorId;
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
        $positions = array_filter(
            $this->currentPositions($quote),
            static fn (int $userId): bool => $operatorId === null || $userId !== $operatorId,
        );

        if ($operatorId === null) {
            unset($positions[ManagerPositions::OPERATOR]);
        } else {
            $positions[ManagerPositions::OPERATOR] = $operatorId;
        }

        return $this->positionsToSlots($positions);
    }

    /**
     * The persisted team as `position => userId` — an explicit query, never a
     * lazy-loaded property access (Model::preventLazyLoading()).
     *
     * @return array<int, int>
     */
    private function currentPositions(Quote $quote): array
    {
        return $quote->managers()->get()
            ->mapWithKeys(static fn (User $manager): array => [
                (int) $manager->pivot->position => (int) $manager->id,
            ])
            ->all();
    }

    /**
     * The same `position => userId` view of a SUBMITTED slot list, read
     * through the shared ManagerPositions::syncMap() so this class can never
     * interpret the payload differently from the writer that will persist it.
     *
     * @param  array<int, int|null>  $slots
     * @return array<int, int>
     */
    private function submittedPositions(array $slots): array
    {
        $byUser = array_map(
            static fn (array $pivot): int => $pivot['position'],
            ManagerPositions::syncMap($slots),
        );

        return array_flip($byUser);
    }

    /**
     * The inverse projection: a `position => userId` map back into the
     * ordered, gap-aware list the writer consumes and the wire carries.
     *
     * @param  array<int, int>  $positions
     * @return array<int, int|null>
     */
    private function positionsToSlots(array $positions): array
    {
        if ($positions === []) {
            return [];
        }

        $slots = array_fill(0, max(array_keys($positions)), null);

        foreach ($positions as $position => $userId) {
            $slots[$position - 1] = $userId;
        }

        return $slots;
    }
}
