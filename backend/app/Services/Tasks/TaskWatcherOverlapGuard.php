<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use Illuminate\Validation\ValidationException;

/**
 * Observer overlap rule (spec 0118, D-9): an id in `watcher_ids` may not
 * also be the creator, the requester or one of the assignees of the same
 * Task. This FORMALLY RETIRES AC-083 of spec 0101 ("an assignee and a
 * watcher may be the same person"): spec 0116 D-3 had suspended the divieto
 * expressly in AC-083's name, and this spec closes that suspension. The
 * membership ROLES still sum freely otherwise (0116 D-3 stands for
 * everything else): creator + requester remains cumulable, and so does
 * creator/requester + assignee — only the watcher slot is exclusive.
 *
 * The rule is evaluated on the RESULTING sets, never on the submitted keys
 * alone (AC-033): a PATCH that moves someone INTO `assignee_ids` without
 * resubmitting `watcher_ids` must still be refused if that person is
 * already a persisted watcher. Because assignees/watchers are pivots, not
 * plain Task columns, "resulting" cannot be read off the model the way
 * TaskHierarchyGuard reads `parent_task_id` after fill() — TaskService is
 * the one that knows whether a key was submitted (the DTO's `has*Ids()`
 * flags) versus needs to fall back to the persisted pivot, so it resolves
 * the four RESULTING arguments before calling this guard, exactly as it
 * already resolves `taskId`/`parentTaskId` for TaskHierarchyGuard. On
 * create() there is no persisted state to fall back to: the resulting sets
 * ARE the submitted ones, plus the creator taken from the actor.
 *
 * Injected into TaskService and invoked inside the write transaction ahead
 * of save(), the same convention as its two siblings TaskClosureFeedbackGuard
 * and TaskHierarchyGuard (unlike the static, Policy-consumed TaskWriteLock/
 * TaskRecordRoles): a refusal here must leave the Task untouched (AC-032),
 * which only holds if the check runs before the row is written.
 *
 * The refusal is a 422 raised FIELD-SCOPED on `watcher_ids`, not a bare
 * `abort()` — same reasoning the sibling guards document: a message-only
 * 422 carrying no `errors` block is swallowed by the shared client helper
 * `frontend/src/features/auth/form-errors.ts`, and Save would appear to do
 * nothing.
 */
final class TaskWatcherOverlapGuard
{
    /**
     * @param  int  $creatorId  the Task's creator (immutable, always present)
     * @param  int|null  $requesterId  the RESULTING requester
     * @param  array<int, int>  $assigneeIds  the RESULTING assignees
     * @param  array<int, int>  $watcherIds  the RESULTING watchers
     *
     * @throws ValidationException 422 on `watcher_ids`
     */
    public function assertNoOverlap(int $creatorId, ?int $requesterId, array $assigneeIds, array $watcherIds): void
    {
        $reserved = $requesterId === null
            ? [$creatorId, ...$assigneeIds]
            : [$creatorId, $requesterId, ...$assigneeIds];

        if (array_intersect($watcherIds, $reserved) !== []) {
            throw ValidationException::withMessages([
                'watcher_ids' => ['An observer cannot also be the creator, the requester or an assignee of this task.'],
            ]);
        }
    }
}
