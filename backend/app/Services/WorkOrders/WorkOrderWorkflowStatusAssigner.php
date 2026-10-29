<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderWorkflowStatusSystemKey;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Owns `work_orders.work_order_workflow_status_id` on the write side (spec
 * 0322, D-1/D-8), the single entry point WorkOrderService::create()/update()
 * reach. Twin of App\Services\Quotes\QuoteWorkflowStatusAssigner.
 *
 * The row is inserted with the GLOBAL open placeholder (the column is NOT
 * NULL and the lines that drive the resolution are attached only after the
 * insert); assign() then moves it onto the status of the set resolved from the
 * lines as they are NOW, applying an explicit client choice on top.
 */
final class WorkOrderWorkflowStatusAssigner
{
    private const string FIELD = 'work_order_workflow_status_id';

    private const string FORCE_CLOSE_FIELD = 'force_close_status_id';

    private const string LOG_DESCRIPTION = 'Work order working status change';

    public function __construct(private readonly WorkOrderWorkflowResolver $resolver) {}

    /**
     * The `open` row of the GLOBAL default set: the insert-time placeholder,
     * remapped onto the resolved set by its shared `system_key`.
     */
    public function globalDefaultOpenStatusId(): int
    {
        $id = WorkOrderWorkflowStatus::query()
            ->whereNull('work_order_workflow_id')
            ->where('system_key', WorkOrderWorkflowStatusSystemKey::Open->value)
            ->value('id');

        if ($id === null) {
            // Defense in depth: the default set is always seeded with its open row.
            abort(500, 'The global default work order workflow status set has no open system row.');
        }

        return (int) $id;
    }

    /**
     * Persist the status the commessa carries given its lines right now:
     * the resolver's baseline (current status kept when still in the set,
     * else same system_key, else open), then $submittedStatusId when it
     * differs. A status outside the resolved set is a 422 (AC-010). When
     * $logChange is set, a status that actually changed leaves an activity
     * entry (the column is outside #[Fillable], so the automatic log never
     * sees it).
     *
     * @throws ValidationException $submittedStatusId is not in the resolved set
     */
    public function assign(WorkOrder $workOrder, ?int $submittedStatusId, bool $logChange): void
    {
        // Step 1: the lines may have just been synced; never trust a stale relation.
        $workOrder->unsetRelation('quoteLines');
        $previousStatusId = $workOrder->work_order_workflow_status_id;

        // Step 2: the baseline of the set the lines resolve to.
        $workflow = $this->resolver->resolve($workOrder);
        $statuses = $this->resolver->statusesFor($workflow);
        $targetId = $this->resolver->targetStatus($workOrder, $workflow)->id;

        // Step 3: an explicit choice must belong to that very set.
        if ($submittedStatusId !== null && $submittedStatusId !== $targetId) {
            if (! $statuses->contains('id', $submittedStatusId)) {
                throw ValidationException::withMessages([
                    self::FIELD => ["The selected status does not belong to the work order's resolved workflow."],
                ]);
            }

            $targetId = $submittedStatusId;
        }

        // Step 4: persist and trace.
        $this->persist($workOrder, $previousStatusId, $targetId, $logChange);
    }

    /**
     * Follow a force-close transition (spec 0322 amendment, spec 0324 D-6/D-7):
     * closing moves the commessa onto $forceCloseStatusId when given (it must
     * be a `closed`-group row of its resolved set, else a 422) or onto the
     * `closed` system row; reopening moves it onto `open` when it sits on ANY
     * `closed`-group row, so a positive or working status stays. The chosen
     * status is ignored when there is no closing transition.
     *
     * @throws ValidationException $forceCloseStatusId is not a closed-group status of the set
     */
    public function followForceClose(WorkOrder $workOrder, bool $wasForceClosed, bool $logChange, ?int $forceCloseStatusId = null): void
    {
        $isForceClosed = (bool) $workOrder->is_force_closed;

        if ($wasForceClosed === $isForceClosed) {
            return;
        }

        // Step 1: the set the lines resolve to right now.
        $workOrder->unsetRelation('quoteLines');
        $previousStatusId = $workOrder->work_order_workflow_status_id;
        $statuses = $this->resolver->statusesFor($this->resolver->resolve($workOrder));

        // Step 2: closing lands on the chosen/keyed closed row; reopening leaves any closed row for `open`.
        $target = $isForceClosed
            ? $this->closingStatus($statuses, $forceCloseStatusId)
            : $this->reopenStatus($statuses, $previousStatusId);

        if ($target === null) {
            return;
        }

        // Step 3: persist and trace.
        $this->persist($workOrder, $previousStatusId, $target->id, $logChange);
    }

    /**
     * @param  Collection<int, WorkOrderWorkflowStatus>  $statuses
     */
    private function closingStatus(Collection $statuses, ?int $forceCloseStatusId): WorkOrderWorkflowStatus
    {
        if ($forceCloseStatusId === null) {
            return $this->systemRow($statuses, WorkOrderWorkflowStatusSystemKey::Closed);
        }

        $chosen = $statuses->firstWhere('id', $forceCloseStatusId);

        if ($chosen === null || $chosen->group !== WorkOrderStatus::Closed) {
            throw ValidationException::withMessages([
                self::FORCE_CLOSE_FIELD => [__('work_orders.workflow_status.force_close_status_invalid')],
            ]);
        }

        return $chosen;
    }

    /**
     * @param  Collection<int, WorkOrderWorkflowStatus>  $statuses
     */
    private function reopenStatus(Collection $statuses, ?int $previousStatusId): ?WorkOrderWorkflowStatus
    {
        $current = $statuses->firstWhere('id', $previousStatusId);

        if ($current?->group !== WorkOrderStatus::Closed) {
            return null;
        }

        return $this->systemRow($statuses, WorkOrderWorkflowStatusSystemKey::Open);
    }

    /**
     * @param  Collection<int, WorkOrderWorkflowStatus>  $statuses
     */
    private function systemRow(Collection $statuses, WorkOrderWorkflowStatusSystemKey $key): WorkOrderWorkflowStatus
    {
        $row = $statuses->firstWhere('system_key', $key->value);

        if ($row === null) {
            // Defense in depth: every set is seeded with its three system rows.
            abort(500, 'The work order workflow status set has no '.$key->value.' system row.');
        }

        return $row;
    }

    /**
     * Spec 0323 D-6/D-1: a task that just closed positively moves its commessa
     * onto the status it carries, even "backwards" (legacy). Returns the
     * status applied, or null when nothing applies (no commessa/status, force
     * closed, or a status outside the commessa's resolved set).
     */
    public function followCompletedTask(Task $task, User $actor): ?WorkOrderWorkflowStatus
    {
        // Step 1: the task must carry both a commessa and a status.
        if ($task->work_order_id === null || $task->work_order_workflow_status_id === null) {
            return null;
        }

        $workOrder = WorkOrder::query()->find($task->work_order_id);

        if ($workOrder === null || $workOrder->is_force_closed) {
            return null;
        }

        // Step 2: D-1, a status outside the resolved set is ignored.
        $workOrder->unsetRelation('quoteLines');
        $statuses = $this->resolver->statusesFor($this->resolver->resolve($workOrder));
        $status = $statuses->firstWhere('id', $task->work_order_workflow_status_id);

        if ($status === null) {
            return null;
        }

        // Step 3: persist and trace, caused by the actor.
        $this->persist($workOrder, $workOrder->work_order_workflow_status_id, $status->id, true, $actor);

        return $status;
    }

    private function persist(WorkOrder $workOrder, ?int $previousStatusId, int $targetId, bool $logChange, ?User $causer = null): void
    {
        if ($targetId === $previousStatusId) {
            return;
        }

        $workOrder->forceFill([self::FIELD => $targetId])->save();
        $workOrder->unsetRelation('workOrderWorkflowStatus');

        if ($logChange) {
            $this->logChange($workOrder, $previousStatusId, $targetId, $causer ?? Auth::user());
        }
    }

    private function logChange(WorkOrder $workOrder, ?int $previousStatusId, int $currentStatusId, ?User $causer): void
    {
        activity($workOrder->getTable())
            ->performedOn($workOrder)
            ->causedBy($causer)
            ->event('updated')
            ->withProperties([
                'attributes' => [self::FIELD => $currentStatusId],
                'old' => [self::FIELD => $previousStatusId],
            ])
            ->log(self::LOG_DESCRIPTION);
    }
}
