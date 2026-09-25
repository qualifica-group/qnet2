<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\DataObjects\TimeEntries\ResolvedTimeEntryLinks;
use App\DataObjects\TimeEntries\TimeEntryData;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use App\Services\Tasks\TaskEffectiveStage;
use App\Services\Tasks\TaskVisibilityScope;
use Illuminate\Validation\ValidationException;

/**
 * THE single implementation of the D-5 link rule (spec 0122): with `task_id`
 * set, the server IMPOSES `title` and the three record links from the Task,
 * ignoring whatever the payload submitted for them; without a Task,
 * opportunity and commessa are mutually exclusive, and either one lends its
 * client to `registry_id` when the payload leaves it blank. Consulted by
 * TimeEntryService on both create() and update() — the ONE place this rule
 * is expressed, so the two write paths cannot drift apart.
 *
 * $owner is the segnatempo's OWNER, never necessarily the acting user: D-5
 * requires the linked Task to be visible to "l'utente del segnatempo" — on
 * a manageAll create-for-another-user that is the target `user_id`, not the
 * admin performing the request; on update() it is the entry's own (immutable)
 * owner.
 *
 * Spec 0163, D-1 extends the same split onto `work_order_stage_id`: with a
 * Task it is imposed from `TaskEffectiveStage::forTask()` — the fase of the
 * task's own ROOT (spec 0167, D-1: a sub-task never carries a fase of its
 * own, `TaskStageGuard`), not necessarily the linked Task itself; without
 * one it is an optional, explicit choice, valid only among the commessa's
 * own OPEN stages. $currentStageId is the voce's stage BEFORE this write
 * (null on create) — D-2's "modificando una voce senza cambiare fase, una
 * fase nel frattempo chiusa resta ammessa" only waives the open-stage check
 * when the submitted stage equals it.
 *
 * Spec 0163 D-2 ("istantanea, mai risincronizzata") is RETIRED by spec 0167,
 * D-2: once written, the fase here IS realigned later, whenever the task's
 * own effective fase changes — `App\Services\Tasks\
 * TaskTimeEntryStageRealigner`, not this class, which only ever runs at
 * WRITE time.
 */
final class TimeEntryLinkResolver
{
    public function resolve(TimeEntryData $data, User $owner, ?int $currentStageId = null): ResolvedTimeEntryLinks
    {
        return $data->taskId !== null
            ? $this->fromTask($data->taskId, $owner)
            : $this->standalone($data, $currentStageId);
    }

    /**
     * @throws ValidationException 422 on `task_id` when the Task does not
     *                             exist or is outside the owner's D-9
     *                             visibility scope.
     */
    private function fromTask(int $taskId, User $owner): ResolvedTimeEntryLinks
    {
        $task = Task::query()->find($taskId);

        if ($task === null || ! TaskVisibilityScope::isVisibleTo($owner, $task)) {
            throw ValidationException::withMessages([
                'task_id' => [__('The selected task is invalid.')],
            ]);
        }

        return new ResolvedTimeEntryLinks(
            title: $task->title,
            registryId: $task->registry_id,
            opportunityId: $task->opportunity_id,
            workOrderId: $task->work_order_id,
            taskId: $task->id,
            workOrderStageId: TaskEffectiveStage::forTask($task),
        );
    }

    /**
     * @throws ValidationException 422 on `work_order_id` when both an
     *                             opportunity and a commessa are submitted
     *                             together, or on `registry_id` when it
     *                             contradicts the client derived from
     *                             either one.
     */
    private function standalone(TimeEntryData $data, ?int $currentStageId): ResolvedTimeEntryLinks
    {
        if ($data->opportunityId !== null && $data->workOrderId !== null) {
            throw ValidationException::withMessages([
                'work_order_id' => [__('An opportunity and a work order cannot both be set.')],
            ]);
        }

        $registryId = $this->coherentRegistryId($data);
        $workOrderStageId = $this->coherentWorkOrderStageId($data, $currentStageId);

        return new ResolvedTimeEntryLinks(
            title: (string) $data->title,
            registryId: $registryId,
            opportunityId: $data->opportunityId,
            workOrderId: $data->workOrderId,
            taskId: null,
            workOrderStageId: $workOrderStageId,
        );
    }

    /**
     * Spec 0163, D-1/AC-002/AC-003: a submitted stage without a commessa is
     * always refused (there is nothing for it to belong to); with a commessa
     * it must belong to THAT one and, unless it is the voce's own unchanged
     * stage (D-2), be currently open.
     *
     * @throws ValidationException
     */
    private function coherentWorkOrderStageId(TimeEntryData $data, ?int $currentStageId): ?int
    {
        if ($data->workOrderStageId === null) {
            return null;
        }

        if ($data->workOrderId === null) {
            throw ValidationException::withMessages([
                'work_order_stage_id' => [__('A work order stage requires a work order.')],
            ]);
        }

        $stage = WorkOrderStage::query()->find($data->workOrderStageId);

        if ($stage === null || $stage->work_order_id !== $data->workOrderId) {
            throw ValidationException::withMessages([
                'work_order_stage_id' => [__('The selected stage does not belong to the selected work order.')],
            ]);
        }

        $stageChanged = $data->workOrderStageId !== $currentStageId;

        if ($stageChanged && $stage->isClosed()) {
            throw ValidationException::withMessages([
                'work_order_stage_id' => [__('This stage is closed.')],
            ]);
        }

        return $stage->id;
    }

    /**
     * The client derived from whichever of opportunity/commessa is set
     * (D-5): a submitted `registry_id` that disagrees with it is a 422; a
     * blank one is filled in from the derived value.
     *
     * @throws ValidationException
     */
    private function coherentRegistryId(TimeEntryData $data): ?int
    {
        $derivedRegistryId = match (true) {
            $data->opportunityId !== null => Opportunity::query()->find($data->opportunityId)?->registry_id,
            $data->workOrderId !== null => WorkOrder::query()
                ->with('quote.opportunity')
                ->find($data->workOrderId)?->quote?->opportunity?->registry_id,
            default => null,
        };

        if ($derivedRegistryId === null) {
            return $data->registryId;
        }

        if ($data->registryId !== null && $data->registryId !== $derivedRegistryId) {
            throw ValidationException::withMessages([
                'registry_id' => [__('The selected client does not match the selected record.')],
            ]);
        }

        return $derivedRegistryId;
    }
}
