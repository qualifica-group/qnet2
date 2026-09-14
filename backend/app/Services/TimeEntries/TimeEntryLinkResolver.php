<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\DataObjects\TimeEntries\ResolvedTimeEntryLinks;
use App\DataObjects\TimeEntries\TimeEntryData;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
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
 */
final class TimeEntryLinkResolver
{
    public function resolve(TimeEntryData $data, User $owner): ResolvedTimeEntryLinks
    {
        return $data->taskId !== null
            ? $this->fromTask($data->taskId, $owner)
            : $this->standalone($data);
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
        );
    }

    /**
     * @throws ValidationException 422 on `work_order_id` when both an
     *                             opportunity and a commessa are submitted
     *                             together, or on `registry_id` when it
     *                             contradicts the client derived from
     *                             either one.
     */
    private function standalone(TimeEntryData $data): ResolvedTimeEntryLinks
    {
        if ($data->opportunityId !== null && $data->workOrderId !== null) {
            throw ValidationException::withMessages([
                'work_order_id' => [__('An opportunity and a work order cannot both be set.')],
            ]);
        }

        $registryId = $this->coherentRegistryId($data);

        return new ResolvedTimeEntryLinks(
            title: (string) $data->title,
            registryId: $registryId,
            opportunityId: $data->opportunityId,
            workOrderId: $data->workOrderId,
            taskId: null,
        );
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
