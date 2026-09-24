<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\TaskStatusSystemKey;
use App\Models\TaskStatus;

/**
 * The creation-time status derivation (spec 0118, D-4; spec 0153, D-4),
 * consumed by App\Services\TaskService::create() and NOTHING else — D-6
 * excludes any re-derivation on PATCH. Read literally, as q-net's own rule
 * states it: with EXACTLY ONE assignee (after dedup) who is the REQUESTER,
 * the Task opens on the `open` row ("Da assegnare"); in EVERY other case —
 * two or more distinct assignees, or a single one who is not the requester —
 * it opens on the `assigned` row ("Assegnato", spec 0118 D-5). The creator no
 * longer counts (spec 0153, D-4 supersedes spec 0118 D-4's own creator
 * carve-out): a creator who assigns solely to themselves, without also being
 * the requester, now lands on `assigned` like any other single assignee who
 * is not the requester.
 *
 * Resolves the destination row by `system_key`, never by label — the
 * convention every class in this namespace that reaches for a protected
 * status row follows (see App\Services\Tasks\TaskCompletionService::systemStatusId()).
 *
 * Injectable rather than static, unlike TaskRecordRoles/TaskAbilityResolver:
 * those two are constrained to zero-argument construction by
 * `permissions:sync`, which instantiates every Policy with `new $class`
 * without a container. This class is consumed by TaskService alone — never a
 * Policy — so it follows TaskService's own constructor-injection convention
 * for its collaborators (TaskClosureFeedbackGuard, TaskHierarchyGuard)
 * instead.
 */
final class TaskInitialStatusResolver
{
    /**
     * `$creatorId` is kept in the signature for source compatibility with
     * App\Services\TaskService::create()'s call site — it is UNUSED by the
     * derivation itself since spec 0153, D-4 ("il creatore non conta piu'").
     *
     * @param  array<int, int>  $assigneeIds
     */
    public function resolve(array $assigneeIds, int $creatorId, ?int $requesterId): int
    {
        return $this->systemStatusId($this->resolveKey($assigneeIds, $requesterId));
    }

    /**
     * @param  array<int, int>  $assigneeIds
     */
    private function resolveKey(array $assigneeIds, ?int $requesterId): TaskStatusSystemKey
    {
        $uniqueAssignees = array_unique($assigneeIds);

        if (count($uniqueAssignees) !== 1) {
            return TaskStatusSystemKey::Assigned;
        }

        $onlyAssignee = reset($uniqueAssignees);

        return $onlyAssignee === $requesterId ? TaskStatusSystemKey::Open : TaskStatusSystemKey::Assigned;
    }

    private function systemStatusId(TaskStatusSystemKey $key): int
    {
        return (int) TaskStatus::query()->where('system_key', $key->value)->value('id');
    }
}
