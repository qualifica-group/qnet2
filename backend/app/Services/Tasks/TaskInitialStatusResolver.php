<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\TaskStatusSystemKey;
use App\Models\TaskStatus;

/**
 * The creation-time status derivation (spec 0118, D-4), consumed by
 * App\Services\TaskService::create() and NOTHING else — D-6 excludes any
 * re-derivation on PATCH. Read literally, as the product document states it:
 * with EXACTLY ONE assignee who is the creator OR the requester, the Task
 * opens on the `open` row ("Da assegnare"); in EVERY other case — two or
 * more assignees, or a single one who is neither — it opens on the
 * `assigned` row ("Assegnato", spec 0118 D-5). The rule applies only to the
 * single-assignee case: two assignees who are both the creator and the
 * requester still land on `assigned`.
 *
 * Resolves the destination row by `system_key`, never by label — the
 * convention every class in this namespace that reaches for a protected
 * status row follows (see App\Services\Tasks\TaskActionService::systemStatusId()).
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
     * @param  array<int, int>  $assigneeIds
     */
    public function resolve(array $assigneeIds, int $creatorId, ?int $requesterId): int
    {
        return $this->systemStatusId($this->resolveKey($assigneeIds, $creatorId, $requesterId));
    }

    /**
     * @param  array<int, int>  $assigneeIds
     */
    private function resolveKey(array $assigneeIds, int $creatorId, ?int $requesterId): TaskStatusSystemKey
    {
        if (count($assigneeIds) !== 1) {
            return TaskStatusSystemKey::Assigned;
        }

        $onlyAssignee = reset($assigneeIds);

        $isCreatorOrRequester = $onlyAssignee === $creatorId || $onlyAssignee === $requesterId;

        return $isCreatorOrRequester ? TaskStatusSystemKey::Open : TaskStatusSystemKey::Assigned;
    }

    private function systemStatusId(TaskStatusSystemKey $key): int
    {
        return (int) TaskStatus::query()->where('system_key', $key->value)->value('id');
    }
}
