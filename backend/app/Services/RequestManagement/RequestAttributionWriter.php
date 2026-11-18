<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\Quote;
use App\Models\User;
use App\Services\Notifications\AssignmentNotifier;
use App\Services\Opportunities\RewardAssignmentWriter;

/**
 * The request's ATTRIBUTION block as the work panel writes it (user directive
 * 2026-07-22, spec 0056/0059/0086/0087/0097): "Fonte", "Segnalatore", "Sede
 * operativa", "Supervisore", the Gestori Account team and the reward assignments —
 * extracted from RequestManagementService, which owns the panel's read/update
 * lifecycle and had grown past the file-size ceiling (engineering.md §6).
 *
 * One class, several entry points rather than one `apply()`: the caller
 * interleaves them with its own steps because they do not all happen at the
 * same moment — the plain scalars are filled BEFORE the models are saved, while
 * the team (pivot rows) and the rewards (their own table) are
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
     * "Segnalatore" (`reporter_id`), "Commerciale" (`commercial_id`, spec
     * 0357), "Sede operativa"
     * (`operational_site_id`), "Supervisore" (`supervisor_id`) and "Fonte"
     * (`source_id`, spec 0332: moved here from the Opportunity), user
     * directive 2026-07-22/spec 0056/spec 0097 D-9 — all on the Quote
     * (D-3). The supervisor is a plain attribution SCALAR here, nothing more:
     * it is the Offerta's commission recipient
     * (`CommissionRecipientRole::Supervisor`), it drives no pivot, no
     * assignment notification and no visibility scope, and it never touches
     * `operator_id`/the OPERATOR slot, which stays the request's own
     * operative ownership (AC-014). That is the whole difference from the
     * deleted RequestSupervisorWriter, which kept the two in lockstep.
     *
     * All five ARE in Quote::$fillable, so this instance's own automatic
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

        foreach (['reporter_id', 'commercial_id', 'operational_site_id', 'supervisor_id', 'source_id'] as $key) {
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
     * The WHOLE team, as the work panel now writes it (spec 0097, D-1/D-5):
     * delegated to the SAME RequestOperatorWriter — which reports
     * `manager_slots` on any genuine move and `operator_id` only when the
     * OPERATOR slot itself changes hands. Every member genuinely ATTACHED to
     * the team is notified against the Offerta (spec 0186, D-2/D-3); one
     * already in it and merely moved, whatever the slot, and a removal notify
     * nobody (decisione utente 2026-11-18: every Gestore Account is the same
     * role, the Operatore slot included).
     *
     * @param  array<int, int|null>  $slots
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    public function applyTeam(Quote $quote, array $slots, User $actor, array &$changed, array &$old): void
    {
        $attached = $this->operatorWriter->applySlots($quote, $slots, $changed, $old);
        $this->assignmentNotifier->notify($quote, $actor, null, $attached);
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
