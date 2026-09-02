<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use App\Services\Notifications\AssignmentNotifier;
use App\Services\Opportunities\RewardAssignmentWriter;
use App\Support\ManagerPositions;

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
        $this->operatorWriter->apply($quote, $value === null ? null : (int) $value, $changed, $old);
        $this->notifyOperatorAssignment($quote, $actor, $changed);
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
