<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\WorkOrderWorkflowStatusSystemKey;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflowStatus;
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
     * Follow a force-close transition (spec 0322 amendment): closing moves the
     * commessa onto the `closed` row ("Chiusa con esito negativo") of its
     * resolved set; reopening moves it back onto `open` only while it still
     * sits on that `closed` row, so a status picked by hand meanwhile stays.
     */
    public function followForceClose(WorkOrder $workOrder, bool $wasForceClosed, bool $logChange): void
    {
        $isForceClosed = (bool) $workOrder->is_force_closed;

        if ($wasForceClosed === $isForceClosed) {
            return;
        }

        // Step 1: the set the lines resolve to right now.
        $workOrder->unsetRelation('quoteLines');
        $previousStatusId = $workOrder->work_order_workflow_status_id;
        $statuses = $this->resolver->statusesFor($this->resolver->resolve($workOrder));
        $closedId = $statuses->firstWhere('system_key', WorkOrderWorkflowStatusSystemKey::Closed->value)?->id;

        // Step 2: closing lands on `closed`; reopening leaves `closed` for `open`.
        $targetKey = match (true) {
            $isForceClosed => WorkOrderWorkflowStatusSystemKey::Closed,
            $previousStatusId === $closedId => WorkOrderWorkflowStatusSystemKey::Open,
            default => null,
        };

        if ($targetKey === null) {
            return;
        }

        $target = $statuses->firstWhere('system_key', $targetKey->value);

        if ($target === null) {
            // Defense in depth: every set is seeded with its three system rows.
            abort(500, 'The work order workflow status set has no '.$targetKey->value.' system row.');
        }

        // Step 3: persist and trace.
        $this->persist($workOrder, $previousStatusId, $target->id, $logChange);
    }

    private function persist(WorkOrder $workOrder, ?int $previousStatusId, int $targetId, bool $logChange): void
    {
        if ($targetId === $previousStatusId) {
            return;
        }

        $workOrder->forceFill([self::FIELD => $targetId])->save();
        $workOrder->unsetRelation('workOrderWorkflowStatus');

        if ($logChange) {
            $this->logChange($workOrder, $previousStatusId, $targetId);
        }
    }

    private function logChange(WorkOrder $workOrder, ?int $previousStatusId, int $currentStatusId): void
    {
        activity($workOrder->getTable())
            ->performedOn($workOrder)
            ->causedBy(Auth::user())
            ->event('updated')
            ->withProperties([
                'attributes' => [self::FIELD => $currentStatusId],
                'old' => [self::FIELD => $previousStatusId],
            ])
            ->log(self::LOG_DESCRIPTION);
    }
}
