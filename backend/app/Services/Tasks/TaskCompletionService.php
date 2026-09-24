<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\DataObjects\Tasks\CompleteTaskData;
use App\Enums\TaskStatusSystemKey;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\Notifications\TaskNotifier;
use App\Services\TaskService;
use App\Services\TimeEntries\TimeEntryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The completion lifecycle of a Task (spec 0116 D-8, spec 0121): complete,
 * uncomplete, approve, reject. Split out of TaskActionService, which keeps
 * the actions that do not move the completion phase (block, unblock,
 * requestUpdate). Each method is a single write inside its own transaction,
 * followed by the SAME detail read TaskController::show uses
 * (TaskService::loadDetail()), so the response is identical in shape to a
 * plain GET.
 *
 * Every method re-asserts what App\Authorization\TasksAuthorization computes
 * for the UI flag: the flag is a suggestion, the Service is the control.
 *  - TaskWriteLock::assertNotBlocked() — D-7 (spec 0153, REQUIREMENT
 *    CHANGED): only complete()/approve() still carry it (409); uncomplete()
 *    and reject() no longer do — they LIFT the block instead, setting
 *    `is_blocked` back to false as part of their own write.
 *  - the record-role matrix (TaskAbilityResolver), re-asserted here because
 *    `Gate::before` for the privileged role bypasses the Policy entirely: a
 *    super-admin who is also an assignee must not validate their own Task
 *    (AC-011, AC-042).
 *  - the injected TaskActionAvailability — WHEN the action makes sense for
 *    the current phase (422), never mixed with the authorization guards (D-1).
 *
 * Notifications (spec 0119, D-12) are one TaskNotifier call per write path,
 * INSIDE the transaction (TaskNotifier defers the send to
 * `DB::afterCommit()`, so a rollback takes it down, AC-028), branching only
 * on what the write path has ALREADY decided: complete() reads the same
 * `$requiresValidation` it branched the status on, uncomplete() reads the
 * phase BEFORE overwriting it.
 */
final class TaskCompletionService
{
    /** D-6, message-only 422 shared by `/complete` and `/approve`. */
    private const string OPEN_SUBTASKS_MESSAGE = 'This task has open sub-tasks: close them before completing it.';

    public function __construct(
        private readonly TaskService $taskService,
        private readonly TaskActionAvailability $availability,
        private readonly TaskClosureFeedbackGuard $closureFeedbackGuard,
        private readonly TaskNotifier $notifier,
        private readonly TimeEntryService $timeEntryService,
    ) {}

    /**
     * The percorso is DERIVED server-side (spec 0121, D-2/D-3), never chosen
     * by the client: `TaskAbilityResolver::completionRequiresValidation()`
     * decides SE, off `requires_validation` and the actor's mandate over
     * $task. The client only ever chooses WHICH `in_validation` status, and
     * only on the validation percorso.
     *
     * PERCORSO VALIDAZIONE: `validation_status_id` is REQUIRED (422 when
     * absent), the Task moves to that status and stays open. PERCORSO
     * CHIUSURA: `validation_status_id` is FORBIDDEN (422 when present), the
     * Task closes positively. Both stamp `completion_date` with today and
     * both are subject to `TaskClosureFeedbackGuard::assertProvided()` (D-4).
     */
    public function complete(Task $task, CompleteTaskData $data, User $actor): Task
    {
        DB::transaction(function () use ($task, $data, $actor): void {
            TaskWriteLock::assertNotBlocked($task);
            $this->assertCompletable($task);
            $this->assertNoOpenSubtasks($task);

            $requiresValidation = TaskAbilityResolver::completionRequiresValidation($actor, $task);

            if ($requiresValidation) {
                $this->assertValidationStatusSubmitted($data);
                $task->task_status_id = $data->validationStatusId;
            } else {
                $this->assertValidationStatusNotSubmitted($data);
                $task->task_status_id = $this->systemStatusId(TaskStatusSystemKey::ClosedPositive);
            }

            if ($data->closureFeedbackSubmitted) {
                $task->closure_feedback = $data->closureFeedback;
            }

            $task->completion_date = now()->toDateString();

            $this->closureFeedbackGuard->assertProvided($task);
            $task->save();

            // D-1/D-2: the segnatempo is part of THIS write, atomic with the
            // status change (any guard above rolls both back together), and
            // needs no `time-entries.create` — completing the Task grants
            // the insert.
            $this->timeEntryService->create($data->timeEntry, $actor);

            if ($requiresValidation) {
                $this->notifier->validationRequested($task, $actor);
            } else {
                // D-11 (spec 0153): assignees are told the closure only when
                // there is more than one — assertNotBlocked() above already
                // guarantees `is_blocked` is false here (D-7).
                $this->notifyClosure($task, $actor, $task->assignees()->count() > 1);
            }
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
        // Read BEFORE the write: afterwards every reopened Task sits on the
        // resume status and the two branches of D-12 would collapse into one.
        $wasInValidation = $this->availability->isValidatable($task);

        DB::transaction(function () use ($task, $actor, $wasInValidation): void {
            // D-7 (spec 0153, REQUIREMENT CHANGED): no `assertNotBlocked()`
            // here — reopening is one of the three actions that LIFT the
            // block, so a blocked Task is admitted, not refused with 409.
            $this->assertUncompletable($task);

            $task->task_status_id = $this->systemStatusId(TaskStatusSystemKey::InProgress);
            $task->closure_feedback = null;
            $task->completion_date = null;
            $task->is_blocked = false;
            $task->save();

            if ($wasInValidation) {
                $this->notifier->validationReopened($task, $actor);
            } else {
                $this->notifier->uncompleted($task, $actor);
            }
        });

        return $this->taskService->loadDetail($task->fresh());
    }

    /**
     * Validates an in-validation Task: closes it positively, same
     * destination status as complete()'s closure percorso.
     */
    public function approve(Task $task, User $actor): Task
    {
        DB::transaction(function () use ($task, $actor): void {
            TaskWriteLock::assertNotBlocked($task);
            $this->assertOwnsMandateForValidation($actor, $task);
            $this->assertValidatable($task);
            $this->assertNoOpenSubtasks($task);

            $task->task_status_id = $this->systemStatusId(TaskStatusSystemKey::ClosedPositive);
            $task->completion_date = now()->toDateString();
            $task->save();

            // Two notifications, not one: voce 4 tells the assignees their
            // work passed, and approve() also CLOSES the Task, which is the
            // trigger voci 2 and 3 describe. D-12 (spec 0153): the assignees
            // already got their own "Approvato" above, so the closure one
            // never repeats it to them (includeAssignees: false) — closure
            // goes to richiedente + osservatori alone.
            $this->notifier->validationApproved($task, $actor);
            $this->notifyClosure($task, $actor, includeAssignees: false);
        });

        return $this->taskService->loadDetail($task->fresh());
    }

    /**
     * Rejects an in-validation Task back onto the `assigned` system status
     * (D-6, spec 0153, REQUIREMENT CHANGED — was `in_progress`, feedback
     * kept), clearing `closure_feedback`: unlike the earlier behaviour, the
     * validator's motivation is not carried forward onto the reopened Task.
     * D-7 (spec 0153, REQUIREMENT CHANGED): no `assertNotBlocked()` — reject
     * is one of the three actions that LIFT the block, admitted on a blocked
     * Task rather than refused with 409.
     */
    public function reject(Task $task, User $actor): Task
    {
        DB::transaction(function () use ($task, $actor): void {
            $this->assertOwnsMandateForValidation($actor, $task);
            $this->assertValidatable($task);

            $task->task_status_id = $this->systemStatusId(TaskStatusSystemKey::Assigned);
            $task->closure_feedback = null;
            $task->completion_date = null;
            $task->is_blocked = false;
            $task->save();

            $this->notifier->validationRejected($task, $actor);
        });

        return $this->taskService->loadDetail($task->fresh());
    }

    /**
     * The "Regola pratica" the document closes with (spec 0119, D-12), shared
     * by the two paths that CLOSE a Task: complete()'s closure percorso and
     * approve(). It reads the feedback the Task CARRIES after the write —
     * which may come from this payload or may already have been on the
     * record — never the submitted flag. $includeAssignees is the D-11/D-12
     * caller-side decision (spec 0153): complete() passes "more than one
     * assignee", approve() always passes false (its own assignees already
     * got voce 4 above).
     */
    private function notifyClosure(Task $task, User $actor, bool $includeAssignees): void
    {
        if (filled($task->closure_feedback)) {
            $this->notifier->feedbackInserted($task, $actor, $includeAssignees);

            return;
        }

        $this->notifier->closed($task, $actor, $includeAssignees);
    }

    /**
     * D-2, re-asserted for approve()/reject(): reads the SAME matrix row the
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

    private function assertCompletable(Task $task): void
    {
        if (! $this->availability->isCompletable($task)) {
            abort(422, 'This task is already closed or awaiting validation.');
        }
    }

    /**
     * D-6, re-asserted here for both `complete()` and `approve()`: the SAME
     * availability query `TasksAuthorization::actionPermissions()` ANDs into
     * the three flags, never a second implementation of the rule.
     */
    private function assertNoOpenSubtasks(Task $task): void
    {
        if ($this->availability->hasOpenSubtasks($task)) {
            abort(422, self::OPEN_SUBTASKS_MESSAGE);
        }
    }

    /**
     * D-3: on the validation percorso, `validation_status_id` is the one
     * thing the FormRequest cannot make required by itself — it does not
     * know which percorso the actor is on.
     */
    private function assertValidationStatusSubmitted(CompleteTaskData $data): void
    {
        if (! $data->validationStatusIdSubmitted) {
            throw ValidationException::withMessages([
                'validation_status_id' => ['A validation status is required to send this task into validation.'],
            ]);
        }
    }

    /**
     * D-3, the mirror case: on the closure percorso a submitted
     * `validation_status_id` is refused rather than silently ignored, so an
     * actor never believes their choice of status was honoured when it was
     * not.
     */
    private function assertValidationStatusNotSubmitted(CompleteTaskData $data): void
    {
        if ($data->validationStatusIdSubmitted) {
            throw ValidationException::withMessages([
                'validation_status_id' => ['A validation status is only accepted when this task requires validation.'],
            ]);
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

    /**
     * The id of the PROTECTED row designated by $key (D-4): `open` and
     * `closed_negative` are never a domain-action destination, so only
     * `in_progress`/`closed_positive`/`assigned` (D-6, spec 0153: reject's
     * new landing status) are ever asked for here.
     */
    private function systemStatusId(TaskStatusSystemKey $key): int
    {
        return (int) TaskStatus::query()->where('system_key', $key->value)->value('id');
    }
}
