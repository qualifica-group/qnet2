<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use App\Services\Assignment\AssignmentCandidates;
use App\Services\Assignment\AssignmentSiteResolver;
use App\Services\Assignment\OperatorCompetence;
use App\Services\Assignment\QuoteCompetence;
use App\Services\Notifications\AssignmentNotifier;
use App\Services\Opportunities\RewardAssignmentWriter;
use App\Support\ManagerPositions;
use Illuminate\Validation\ValidationException;

/**
 * The request's ATTRIBUTION block as the work panel writes it (user directive
 * 2026-07-22, spec 0056/0059/0086/0087/0097): "Fonte", "Segnalatore", "Sede
 * operativa", "Supervisore", the GA2 "Operatore" and the reward assignments —
 * extracted from RequestManagementService, which owns the panel's read/update
 * lifecycle and had grown past the file-size ceiling (engineering.md §6).
 *
 * One class, several entry points rather than one `apply()`: the caller
 * interleaves them with its own steps because they do not all happen at the
 * same moment — the plain scalars are filled BEFORE the models are saved, while
 * the operator slot (a pivot row) and the rewards (their own table) are
 * written AFTER. That ordering is the caller's business, the rules are this
 * class'.
 *
 * Every method reports a genuine change into the caller's $changed/$old pair:
 * the module's operational history stays anchored on the OPPORTUNITY (spec
 * 0049 D-9), which the Quote's own model log never reaches.
 */
final class RequestAttributionWriter
{
    public function __construct(
        private readonly RequestOperatorWriter $operatorWriter,
        private readonly RewardAssignmentWriter $rewardAssignmentWriter,
        private readonly AssignmentNotifier $assignmentNotifier,
        private readonly AssignmentSiteResolver $siteResolver,
        private readonly QuoteCompetence $quoteCompetence,
        private readonly AssignmentCandidates $candidates,
        private readonly OperatorCompetence $competence,
    ) {}

    /**
     * "Fonte" (`source_id`), the one attribution scalar still on the
     * Opportunity (D-2). IS in Opportunity::$fillable, so — unlike the
     * operative fields of this panel — its change is picked up by the
     * automatic activity log (LogsModelActivity::logFillable()); no explicit
     * entry is added for it, which would double-log the same diff.
     *
     * @param  array<string, mixed>  $data
     */
    public function applySource(Opportunity $opportunity, array $data): void
    {
        if (! array_key_exists('source_id', $data)) {
            return;
        }

        $opportunity->fill(['source_id' => $data['source_id']]);
    }

    /**
     * "Segnalatore" (`reporter_id`), "Sede operativa"
     * (`operational_site_id`) and "Supervisore" (`supervisor_id`), user
     * directive 2026-07-22/spec 0056/spec 0097 D-9 — all three on the Quote
     * (D-3). The last one is a plain attribution SCALAR here, nothing more:
     * it is the Offerta's commission recipient
     * (`CommissionRecipientRole::Supervisor`), it drives no pivot, no
     * assignment notification and no visibility scope, and it never touches
     * `operator_id`/the OPERATOR slot, which stays the request's own
     * operative ownership (AC-014). That is the whole difference from the
     * deleted RequestSupervisorWriter, which kept the two in lockstep.
     *
     * All three ARE in Quote::$fillable, so this instance's own automatic
     * activity log is suspended (Quote::disableLogging(),
     * instance-scoped — mirrors RequestTransferService's own discipline) and
     * a genuine change is reported into $changed/$old instead, so the
     * caller's EXPLICIT entry — anchored on the Opportunity (D-9) — stays the
     * one record of it.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    public function applyQuoteAttribution(Quote $quote, array $data, array &$changed, array &$old): void
    {
        $submitted = [];

        foreach (['reporter_id', 'operational_site_id', 'supervisor_id'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key] === null ? null : (int) $data[$key];
            $previous = $quote->getAttribute($key);

            if ($previous === $value) {
                continue;
            }

            $old[$key] = $previous;
            $changed[$key] = $value;
            $submitted[$key] = $value;
        }

        if ($submitted === []) {
            return;
        }

        $quote->disableLogging();
        $quote->fill($submitted);
    }

    /**
     * The GA2 "Operatore" (user directive 2026-07-22; spec 0087, D-9):
     * delegated to RequestOperatorWriter, the ONE implementation of the
     * operator-slot-sync rule shared with the bulk assignment
     * (RequestAssignmentService) and the transfer flow
     * (RequestTransferService). Never touches `quotes.supervisor_id`
     * (D-13/D-14, INV-5).
     *
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    public function applyOperator(Quote $quote, mixed $value, User $actor, array &$changed, array &$old): void
    {
        $operatorId = $value === null ? null : (int) $value;

        $this->assertOperatorCovers($quote, $operatorId);

        $this->operatorWriter->apply($quote, $operatorId, $changed, $old);
        $this->notifyOperatorAssignment($quote, $actor, $changed);
    }

    /**
     * The GA2 cell may only receive an operator the offer itself would have
     * accepted (direttiva utente 2026-09-10): a member of its own
     * `quotes.operational_site_id` (spec 0113, D-4) competent for its own
     * product lines (spec 0110, INV-1). Not re-implemented here — it is the
     * SAME composition the bulk assignment validates against and
     * `mode=balanced` distributes over, so the two channels onto this one
     * slot can never disagree on who is eligible.
     *
     * WHY THIS LIVES IN THE WRITER, not in the table engine: the inline cell
     * reaches this slot through the generic `updateCell` path, whose write
     * check is RelationValueScopeChecker — which resolves the submitted id
     * through `users/for-select` WITHOUT the row's Sede or categories, and
     * receives neither the row nor the column's `relation.scope`. And
     * `relation.lockScope` is NOT a server gate: it is a UI flag telling the
     * editor not to offer the "show everyone" escape (ResolvesColumnConfig),
     * nothing more. Reading it as a gate is the exact misunderstanding that
     * makes this guard necessary. The precedent is `products_of_interest`,
     * which likewise enforces its own coherence in its domain writer.
     *
     * An offer demanding no category is unconstrained on COMPETENCE (INV-4a).
     *
     * An offer with NO Sede is judged on COMPETENCE ALONE here (direttiva
     * utente 2026-09-10) and stays editable — deliberately the OPPOSITE of the
     * bulk endpoint, where a Sede-less offer is incompatible. The two are not
     * inconsistent: in the bulk ONE operator is chosen for EVERY targeted
     * offer, so an offer that can express no candidate would make that single
     * choice arbitrary; here the operator is picked for THIS offer alone.
     * Applying the bulk rule to this path would make a Sede-less offer
     * impossible to assign until somebody gives it a Sede — a dead end whose
     * cause is invisible to whoever hits it. Do not "align" the two.
     *
     * A null CLEARS the slot and is left alone: releasing an offer is not
     * assigning it to anybody, so there is nobody to be ineligible.
     */
    private function assertOperatorCovers(Quote $quote, ?int $operatorId): void
    {
        if ($operatorId === null) {
            return;
        }

        $quoteId = (int) $quote->id;
        $siteByQuote = $this->siteResolver->forQuotes([$quoteId]);
        $requiredByQuote = $this->quoteCompetence->requiredByQuote([$quoteId]);

        // No Sede: competence alone decides, and the offer stays assignable.
        if (($siteByQuote[$quoteId] ?? null) === null) {
            if ($this->competence->competent([$operatorId], $requiredByQuote[$quoteId] ?? []) !== []) {
                return;
            }
        } elseif (in_array($operatorId, $this->candidates->byRecord($siteByQuote, $requiredByQuote)[$quoteId] ?? [], true)) {
            return;
        }

        throw ValidationException::withMessages([
            'operator_id' => [__('The chosen operator is not enabled for the Sede or the product categories of this request.')],
        ]);
    }

    /**
     * The GA1 slot (direttiva utente 2026-09-07, moved onto position 1 by
     * the direttiva utente 2026-09-08): the grid's other Gestore Account cell, delegated to the SAME RequestOperatorWriter as the
     * Operatore above. NO notification follows it, deliberately — GA1 scopes
     * no visibility and assigns nobody, exactly like a reshuffle of the other
     * slots in applyTeam() (spec 0097, D-6/AC-007).
     *
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    public function applyManagerGa1(Quote $quote, mixed $value, array &$changed, array &$old): void
    {
        $this->operatorWriter->applyGa1($quote, $value === null ? null : (int) $value, $changed, $old);
    }

    /**
     * The WHOLE team, as the work panel now writes it (spec 0097, D-1/D-5):
     * delegated to the SAME RequestOperatorWriter — which reports
     * `manager_slots` on any genuine move and `operator_id` only when the
     * OPERATOR slot itself changes hands — and then through the SAME
     * notification path as applyOperator() above. That is the whole point of
     * D-6: an assignment is an assignment regardless of which editor produced
     * it, and a reshuffle that leaves the operator in place notifies nobody.
     *
     * @param  array<int, int|null>  $slots
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    public function applyTeam(Quote $quote, array $slots, User $actor, array &$changed, array &$old): void
    {
        $this->operatorWriter->applySlots($quote, $slots, $changed, $old);
        $this->notifyOperatorAssignment($quote, $actor, $changed);
    }

    /**
     * spec 0081: only a GENUINE transition is an assignment — the writer
     * leaves `operator_id` unset in $changed when the slot already held this
     * user, which is exactly the "renamed nothing" case that must notify
     * nobody. Notified against the Opportunity: the notification detail card
     * only understands Registry/Opportunity records (D-2).
     *
     * @param  array<string, mixed>  $changed
     */
    private function notifyOperatorAssignment(Quote $quote, User $actor, array $changed): void
    {
        $newOperatorId = $changed['operator_id'] ?? null;

        if ($newOperatorId === null) {
            return;
        }

        $this->assignmentNotifier->notify(
            $quote->opportunity,
            $actor,
            null,
            [$newOperatorId => ManagerPositions::OPERATOR],
            // Spec 0086, MT-04b: the deep link's request-management branch
            // must open THIS Offerta, not the Opportunity — the two ids
            // diverged since the grid row migrated onto the Quote.
            requestManagementRecordId: $quote->id,
            // Spec 0087, D-10: the detail card's "operator" field reads THIS
            // Offerta's own GA2, not the parent Opportunity's.
            requestManagementQuote: $quote,
        );
    }

    /**
     * Reward assignments (spec 0059, AC-023; spec 0086, D-4/D-12 — the owner
     * is now the Offerta, its beneficiary the Offerta's OWN Segnalatore).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    public function applyRewards(Quote $quote, ?int $previousReporterId, array $data, array &$changed, array &$old): void
    {
        $reporterChanged = array_key_exists('reporter_id', $data) && $quote->reporter_id !== $previousReporterId;

        if ($reporterChanged) {
            $this->rewardAssignmentWriter->retarget($quote);
        }

        if (! array_key_exists('rewards', $data)) {
            return;
        }

        $current = $quote->rewards()->pluck('reward_type_id')->map(intval(...))->sort()->values()->all();
        $next = collect((array) $data['rewards'])
            ->map(static fn (array $row): int => (int) $row['reward_type_id'])
            ->unique()->sort()->values()->all();

        if ($current === $next) {
            return;
        }

        $this->rewardAssignmentWriter->sync($quote, $next);

        $old['rewards'] = $current;
        $changed['rewards'] = $next;
    }
}
