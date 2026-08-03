<?php

declare(strict_types=1);

namespace App\Services\Rewards;

use App\Enums\StatusSystemKey;
use App\Enums\WorkflowStatusGroup;
use App\Models\Opportunity;
use App\Models\OpportunityWorkflowStatus;
use App\Models\Reward;
use App\Models\RewardStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The request -> reward lifecycle automation (spec 0073): when an Opportunity
 * enters a `closed_lost` WORKING status (`opportunity_workflow_status_id`,
 * App\Enums\WorkflowStatusGroup — D-1), every reward born from it is closed
 * negatively; when the request reopens, each one goes back to the status it
 * carried before (D-2).
 *
 * DIVERGENCE from its template, App\Services\Contracts\ContractLifecycleManager,
 * which diffs the status group BEFORE/AFTER the write: this one RECONCILES
 * (D-7). The reason is concrete — the working status is written from three
 * different places, and a reward can be CREATED in the very transaction that
 * closes the request (RequestManagementService::updateWork() writes the
 * status in Step 3 and syncs the rewards in Step 8), which a before/after
 * diff would miss. Reconciling against the CURRENT status instead makes the
 * call idempotent, so callers may invoke it once per transaction without
 * tracking what changed.
 *
 * `status_before_closure_id` is both the saved status and the "closed by the
 * automation" marker: non-null means the current status was imposed here, so
 * a second closing pass never overwrites the original value and a reopening
 * pass never touches a reward a human owns.
 */
final class RewardLifecycleManager
{
    /**
     * Aligns $opportunity's rewards with its CURRENT working status. Runs
     * inside the caller's transaction (spec 0073 constraints): a later
     * failure must roll the reward movements back too.
     */
    public function reconcile(Opportunity $opportunity): void
    {
        // Step 1: the phase the request is in right now. A request with no
        // working status at all is not closed, so it falls in the reopen
        // branch (a no-op unless the automation had closed something).
        $isClosedLost = $this->currentGroup($opportunity) === WorkflowStatusGroup::ClosedLost;

        // Step 2: only the rewards this automation cares about — closing
        // looks at the ones it has not closed yet, reopening at the ones it
        // did (the marker column, D-2).
        /** @var Collection<int, Reward> $rewards */
        $rewards = $opportunity->rewards()
            ->when(
                $isClosedLost,
                static fn (Builder $query): Builder => $query->whereNull('status_before_closure_id'),
                static fn (Builder $query): Builder => $query->whereNotNull('status_before_closure_id'),
            )
            ->get();

        if ($rewards->isEmpty()) {
            return;
        }

        // Step 3: apply. Per-model save, never a bulk query-builder update:
        // LogsModelActivity hooks Eloquent's saving/saved events, which a bulk
        // UPDATE bypasses entirely (same rationale as
        // RewardAssignmentWriter::retarget()).
        $isClosedLost
            ? $this->close($rewards, $this->systemStatusId(StatusSystemKey::Lost))
            : $this->reopen($rewards);

        $opportunity->unsetRelation('rewards');
    }

    private function currentGroup(Opportunity $opportunity): ?WorkflowStatusGroup
    {
        $statusId = $opportunity->opportunity_workflow_status_id;

        return $statusId === null ? null : OpportunityWorkflowStatus::query()->find($statusId)?->group;
    }

    /**
     * @param  Collection<int, Reward>  $rewards
     */
    private function close(Collection $rewards, int $closedLostStatusId): void
    {
        foreach ($rewards as $reward) {
            if ($reward->reward_status_id === $closedLostStatusId) {
                continue; // already sitting on the closing row by a human's own choice: nothing to save, nothing to restore later.
            }

            $reward->status_before_closure_id = $reward->reward_status_id;
            $reward->reward_status_id = $closedLostStatusId;
            $reward->save();

            $this->log($reward, 'reward.auto_closed', 'Reward closed by its request');
        }
    }

    /**
     * @param  Collection<int, Reward>  $rewards
     */
    private function reopen(Collection $rewards): void
    {
        foreach ($rewards as $reward) {
            $reward->reward_status_id = $reward->status_before_closure_id;
            $reward->status_before_closure_id = null;
            $reward->save();

            $this->log($reward, 'reward.auto_reopened', 'Reward reopened with its request');
        }
    }

    /**
     * The id of a system reward status, resolved by `system_key` and never
     * hardcoded (spec 0060 BR-6's rule, shared with
     * RewardAssignmentWriter::resolvePendingStatusId()).
     */
    private function systemStatusId(StatusSystemKey $systemKey): int
    {
        return (int) RewardStatus::query()->where('system_key', $systemKey->value)->value('id');
    }

    /**
     * An explicit event name (spec 0073, D-9) so the reward's history tells an
     * automatic movement apart from a manual one — same convention as
     * ContractLifecycleManager's `contract.suspended`.
     */
    private function log(Reward $reward, string $event, string $description): void
    {
        activity($reward->getTable())->performedOn($reward)->event($event)->log($description);
    }
}
