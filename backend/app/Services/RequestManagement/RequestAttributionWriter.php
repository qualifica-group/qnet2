<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use App\Services\Notifications\AssignmentNotifier;
use App\Services\Opportunities\RewardAssignmentWriter;

/**
 * The request's ATTRIBUTION block as the work panel writes it (user directive
 * 2026-07-22, spec 0056/0059/0086): "Fonte", "Segnalatore", "Sede operativa",
 * the GA2 "Operatore"/Supervisore and the reward assignments — extracted from
 * RequestManagementService, which owns the panel's read/update lifecycle and
 * had grown past the file-size ceiling (engineering.md §6).
 *
 * One class, four entry points rather than one `apply()`: the caller
 * interleaves them with its own steps because they do not all happen at the
 * same moment — the two scalars are filled BEFORE the models are saved, while
 * the supervisor slot (a pivot row) and the rewards (their own table) are
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
        private readonly RequestSupervisorWriter $supervisorWriter,
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
     * "Segnalatore" (`reporter_id`) and "Sede operativa"
     * (`operational_site_id`), user directive 2026-07-22/spec 0056 — moved
     * onto the Quote (D-3). Both ARE in Quote::$fillable, so this instance's
     * own automatic activity log is suspended (Quote::disableLogging(),
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

        foreach (['reporter_id', 'operational_site_id'] as $key) {
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
     * The GA2 "Operatore" / Supervisore (user directive 2026-07-22; spec
     * 0086, D-3): delegated to RequestSupervisorWriter, the ONE
     * implementation of the supervisor-slot-sync rule shared with the bulk
     * assignment (RequestAssignmentService) and the transfer flow
     * (RequestTransferService).
     *
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    public function applySupervisor(Quote $quote, mixed $value, User $actor, array &$changed, array &$old): void
    {
        $this->supervisorWriter->apply($quote, $value === null ? null : (int) $value, $changed, $old);

        // spec 0081: only a GENUINE transition is an assignment — apply()
        // leaves `operator_id` unset in $changed when the slot already held
        // this user, which is exactly the "renamed nothing" case that must
        // notify nobody. Notified against the Opportunity: the notification
        // detail card only understands Registry/Opportunity records (D-2).
        $newSupervisorId = $changed['operator_id'] ?? null;

        if ($newSupervisorId === null) {
            return;
        }

        $this->assignmentNotifier->notify(
            $quote->opportunity,
            $actor,
            null,
            [$newSupervisorId => Opportunity::OPERATOR_MANAGER_POSITION],
            // Spec 0086, MT-04b: the deep link's request-management branch
            // must open THIS Offerta, not the Opportunity — the two ids
            // diverged since the grid row migrated onto the Quote.
            requestManagementRecordId: $quote->id,
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
