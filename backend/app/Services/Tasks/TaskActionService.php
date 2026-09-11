<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\DataObjects\Tasks\CompleteTaskData;
use App\Enums\TaskStatusSystemKey;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Support\Facades\DB;

/**
 * The six domain actions that move a Task's STATE (spec 0116, D-8):
 * complete, uncomplete, approve, reject, block, unblock. Each is a single,
 * small write inside its own transaction, followed by the SAME detail read
 * TaskController::show uses (TaskService::loadDetail()), so every action's
 * response is identical in shape to a plain GET — the same contract
 * App\Services\ContractActionService keeps for the Contracts module this
 * class is modelled on.
 *
 * Every method re-asserts, on top of what the controller's
 * `$this->authorize()` already checked, exactly what
 * App\Authorization\TasksAuthorization::actionPermissions() computes for the
 * UI flag: the flag is a suggestion, the Service is the control (class
 * docblock constraint). Three guards recur across the six:
 *  - assertNotBlocked() — D-8: an `is_blocked` Task admits only unblock()
 *    (409), never block() itself, whose own availability check already
 *    covers "not already blocked".
 *  - assertOwnsMandate() — D-2's admin-as-assignee deroga, re-asserted here
 *    (not only in TaskPolicy) because `Gate::before` for the privileged role
 *    bypasses the Policy entirely: without this a super-admin who is also
 *    an assignee would validate or block their own Task (AC-011). Reads
 *    `TaskAbilityResolver::canValidate()`/`canBlock()` — the SAME matrix the
 *    Policy consults, never a re-implementation of the rule.
 *  - the injected TaskActionAvailability — WHEN the action makes sense for
 *    the Task's current phase (422 when it does not), never mixed with the
 *    two guards above, which are pure authorization concerns (D-1).
 *
 * D-9 (notifications) and D-10 (timesheet) are OUT OF SCOPE: each method
 * ends with its `return $this->taskService->loadDetail(...)` — the
 * DECLARED insertion point for both, added just before that line once their
 * own microtask lands.
 */
final class TaskActionService
{
    public function __construct(
        private readonly TaskService $taskService,
        private readonly TaskActionAvailability $availability,
        private readonly TaskClosureFeedbackGuard $closureFeedbackGuard,
    ) {}

    /**
     * CASO 1 (no `validation_status_id`): the Task closes positively.
     * CASO 2 (submitted): the Task moves to the caller-chosen
     * `in_validation` status instead, and stays open. Both cases stamp
     * `completion_date` with today; only CASO 1 can trip
     * TaskClosureFeedbackGuard, since `in_validation` is never a CLOSING
     * phase (App\Enums\TaskStatusGroup::isClosing()).
     */
    public function complete(Task $task, CompleteTaskData $data, User $actor): Task
    {
        DB::transaction(function () use ($task, $data): void {
            $this->assertNotBlocked($task);
            $this->assertCompletable($task);

            $task->task_status_id = $data->validationStatusIdSubmitted
                ? $data->validationStatusId
                : $this->systemStatusId(TaskStatusSystemKey::ClosedPositive);

            if ($data->closureFeedbackSubmitted) {
                $task->closure_feedback = $data->closureFeedback;
            }

            $task->completion_date = now()->toDateString();

            $this->closureFeedbackGuard->assertSatisfied($task);
            $task->save();
        });

        return $this->taskService->loadDetail($task->fresh());
    }

    /**
     * Reopens a closed or in-validation Task onto the designated resume
     * status (D-4). `closure_feedback` is cleared: unlike reject(), this is
     * the actor undoing their OWN completion, not a validator's decision
     * carrying a motivation worth keeping.
     */
    public function uncomplete(Task $task, User $actor): Task
    {
        DB::transaction(function () use ($task): void {
            $this->assertNotBlocked($task);
            $this->assertUncompletable($task);

            $task->task_status_id = $this->systemStatusId(TaskStatusSystemKey::InProgress);
            $task->closure_feedback = null;
            $task->completion_date = null;
            $task->save();
        });

        return $this->taskService->loadDetail($task->fresh());
    }

    /**
     * Validates an in-validation Task: closes it positively, same
     * destination status as complete()'s CASO 1.
     */
    public function approve(Task $task, User $actor): Task
    {
        DB::transaction(function () use ($task, $actor): void {
            $this->assertNotBlocked($task);
            $this->assertOwnsMandateForValidation($actor, $task);
            $this->assertValidatable($task);

            $task->task_status_id = $this->systemStatusId(TaskStatusSystemKey::ClosedPositive);
            $task->completion_date = now()->toDateString();
            $task->save();
        });

        return $this->taskService->loadDetail($task->fresh());
    }

    /**
     * Rejects an in-validation Task back onto the resume status.
     * `closure_feedback` is DELIBERATELY left untouched: it is the
     * motivation the validator decided against, not the actor's own note —
     * the one deliberate difference from uncomplete().
     */
    public function reject(Task $task, User $actor): Task
    {
        DB::transaction(function () use ($task, $actor): void {
            $this->assertNotBlocked($task);
            $this->assertOwnsMandateForValidation($actor, $task);
            $this->assertValidatable($task);

            $task->task_status_id = $this->systemStatusId(TaskStatusSystemKey::InProgress);
            $task->completion_date = null;
            $task->save();
        });

        return $this->taskService->loadDetail($task->fresh());
    }

    /**
     * Freezes the Task (D-8): every OTHER action refuses with 409 while
     * `is_blocked` is true. Not itself gated by assertNotBlocked() — a
     * second block() is refused by assertBlockable() instead (422), which
     * is the correct status for "already in the state you asked for".
     */
    public function block(Task $task, User $actor): Task
    {
        DB::transaction(function () use ($task, $actor): void {
            $this->assertOwnsMandateForBlocking($actor, $task);
            $this->assertBlockable($task);

            $task->is_blocked = true;
            $task->save();
        });

        return $this->taskService->loadDetail($task->fresh());
    }

    /**
     * Lifts the freeze. The only action a blocked Task ever admits, so it
     * carries no assertNotBlocked() of its own.
     */
    public function unblock(Task $task, User $actor): Task
    {
        DB::transaction(function () use ($task, $actor): void {
            $this->assertOwnsMandateForBlocking($actor, $task);
            $this->assertUnblockable($task);

            $task->is_blocked = false;
            $task->save();
        });

        return $this->taskService->loadDetail($task->fresh());
    }

    /**
     * D-8: a frozen Task admits no state-changing action except unblock().
     */
    private function assertNotBlocked(Task $task): void
    {
        if ($task->is_blocked) {
            abort(409, 'This task is blocked: unblock it before performing this action.');
        }
    }

    /**
     * D-2, re-asserted for approve()/reject(): `Gate::before` bypasses
     * TaskPolicy for the privileged role, so an actor who is ALSO an
     * assignee must be refused here too, whether or not they hold
     * `tasks.manageAll` (AC-010/AC-011). Reads the SAME matrix row the
     * Policy consults (TaskAbilityResolver::canValidate()), never a second
     * implementation of the rule.
     */
    private function assertOwnsMandateForValidation(User $actor, Task $task): void
    {
        abort_unless(
            TaskAbilityResolver::canValidate($actor, $task),
            403,
            'Only the creator, the requester or a manager may validate this task.',
        );
    }

    /**
     * The mirror of assertOwnsMandateForValidation() for block()/unblock()
     * (D-2, D-8): "bloccare" in the document's deroga covers both
     * directions, both gated by the same `tasks.block` ability and matrix
     * row.
     */
    private function assertOwnsMandateForBlocking(User $actor, Task $task): void
    {
        abort_unless(
            TaskAbilityResolver::canBlock($actor, $task),
            403,
            'Only the creator, the requester or a manager may block this task.',
        );
    }

    private function assertCompletable(Task $task): void
    {
        if (! $this->availability->isCompletable($task)) {
            abort(422, 'This task is already closed or awaiting validation.');
        }
    }

    private function assertUncompletable(Task $task): void
    {
        if (! $this->availability->isUncompletable($task)) {
            abort(422, 'This task is neither closed nor awaiting validation.');
        }
    }

    private function assertValidatable(Task $task): void
    {
        if (! $this->availability->isValidatable($task)) {
            abort(422, 'This task is not awaiting validation.');
        }
    }

    private function assertBlockable(Task $task): void
    {
        if (! $this->availability->isBlockable($task)) {
            abort(422, 'This task is already blocked.');
        }
    }

    private function assertUnblockable(Task $task): void
    {
        if (! $this->availability->isUnblockable($task)) {
            abort(422, 'This task is not blocked.');
        }
    }

    /**
     * The id of the PROTECTED row designated by $key (D-4): `open` and
     * `closed_negative` are never a domain-action destination, so only
     * `in_progress`/`closed_positive` are ever asked for here.
     */
    private function systemStatusId(TaskStatusSystemKey $key): int
    {
        return (int) TaskStatus::query()->where('system_key', $key->value)->value('id');
    }
}
