<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\DataObjects\Tasks\CompleteTaskData;
use App\DataObjects\Tasks\RequestTaskUpdateData;
use App\Enums\TaskStatusSystemKey;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Notifications\TaskUpdateRequested;
use App\Services\Notifications\TaskNotifier;
use App\Services\TaskService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The seven domain actions that move a Task's STATE, or ask someone about
 * it (spec 0116, D-8; spec 0118, D-10..D-14): complete, uncomplete, approve,
 * reject, block, unblock, requestUpdate. Six of the seven are a single,
 * small write inside their own transaction, followed by the SAME detail
 * read TaskController::show uses (TaskService::loadDetail()), so every
 * action's response is identical in shape to a plain GET — the same
 * contract App\Services\ContractActionService keeps for the Contracts
 * module this class is modelled on. requestUpdate() is the one exception:
 * it writes nothing to the Task at all (see its own docblock).
 *
 * Every method re-asserts, on top of what the controller's
 * `$this->authorize()` already checked, exactly what
 * App\Authorization\TasksAuthorization::actionPermissions() computes for the
 * UI flag: the flag is a suggestion, the Service is the control (class
 * docblock constraint). Three guards recur across the seven:
 *  - assertNotBlocked() — D-8: an `is_blocked` Task admits only unblock()
 *    (409), never block() itself, whose own availability check already
 *    covers "not already blocked".
 *  - the record-role matrix, re-asserted here (not only in TaskPolicy)
 *    because `Gate::before` for the privileged role bypasses the Policy
 *    entirely: without this a super-admin who is also an assignee would
 *    validate, block or request an update on their own Task (AC-011,
 *    AC-042). Reads `TaskAbilityResolver::canValidate()`/`canBlock()`/
 *    `canRequestUpdate()` — the SAME matrix the Policy consults, never a
 *    re-implementation of the rule.
 *  - the injected TaskActionAvailability — WHEN the action makes sense for
 *    the Task's current phase (422 when it does not), never mixed with the
 *    two guards above, which are pure authorization concerns (D-1).
 *
 * D-9 (timesheet, renumbered D-10 in spec 0118) is OUT OF SCOPE: each of the
 * six state-changing methods ends with its
 * `return $this->taskService->loadDetail(...)` — the DECLARED insertion
 * point, added just before that line once its own microtask lands.
 *
 * Notifications (spec 0119, D-12) now leave all seven methods, but never as
 * logic: each write path says WHAT happened with a single call to the
 * injected TaskNotifier, which alone knows WHO hears about it. Two rules
 * govern those calls. They stay INSIDE the transaction, because
 * TaskNotifier defers the actual send to `DB::afterCommit()` — a rollback
 * must take the notification down with it (AC-028). And they branch only on
 * what the write path has ALREADY decided, never on a second evaluation of
 * the domain: complete() reads the SAME `$requiresValidation` flag it just
 * branched the status on (spec 0121, D-2 — no longer the client's own
 * submitted flag), and uncomplete() reads the phase it is about to overwrite
 * BEFORE overwriting it, since afterwards every Task looks alike.
 * requestUpdate() keeps its own spec 0118 notification, sent to the
 * caller-chosen recipients alone and untouched by 0119.
 */
final class TaskActionService
{
    public function __construct(
        private readonly TaskService $taskService,
        private readonly TaskActionAvailability $availability,
        private readonly TaskClosureFeedbackGuard $closureFeedbackGuard,
        private readonly TaskNotifier $notifier,
    ) {}

    /**
     * The percorso is DERIVED server-side (spec 0121, D-2/D-3), never chosen
     * by the client: `TaskAbilityResolver::completionRequiresValidation()`
     * decides SE, off `requires_validation` and the actor's mandate over
     * $task. The client only ever chooses WHICH `in_validation` status, and
     * only on the validation percorso — `CompleteTaskData` carries no flag
     * of its own to branch on any more, unlike before this spec.
     *
     * PERCORSO VALIDAZIONE: `validation_status_id` is REQUIRED (422 when
     * absent), the Task moves to that status and stays open. PERCORSO
     * CHIUSURA: `validation_status_id` is FORBIDDEN (422 when present), the
     * Task closes positively. Both stamp `completion_date` with today and
     * both are subject to `TaskClosureFeedbackGuard::assertProvided()` (D-4):
     * unlike the retired CASO 1/CASO 2 split, the feedback requirement no
     * longer depends on which percorso was taken.
     */
    public function complete(Task $task, CompleteTaskData $data, User $actor): Task
    {
        DB::transaction(function () use ($task, $data, $actor): void {
            $this->assertNotBlocked($task);
            $this->assertCompletable($task);

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

            if ($requiresValidation) {
                $this->notifier->validationRequested($task, $actor);
            } else {
                $this->notifyClosure($task, $actor);
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
            $this->assertNotBlocked($task);
            $this->assertUncompletable($task);

            $task->task_status_id = $this->systemStatusId(TaskStatusSystemKey::InProgress);
            $task->closure_feedback = null;
            $task->completion_date = null;
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

            // Two notifications, not one: voce 4 tells the assignees their
            // work passed, and approve() also CLOSES the Task, which is the
            // trigger voci 2 and 3 describe.
            $this->notifier->validationApproved($task, $actor);
            $this->notifyClosure($task, $actor);
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

            $this->notifier->validationRejected($task, $actor);
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

            $this->notifier->locked($task, $actor);
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

            $this->notifier->unlocked($task, $actor);
        });

        return $this->taskService->loadDetail($task->fresh());
    }

    /**
     * "Richiedi aggiornamento" (spec 0118, D-10..D-14): does NOT open a
     * transaction, unlike its six siblings — there is nothing to write to
     * the Task and therefore nothing to roll back. Availability re-uses
     * `isCompletable()`, the SAME rule complete() enforces (D-10 forbids a
     * twin method); the matrix (`canRequestUpdate()`) is re-asserted here
     * past `Gate::before`, exactly like the other mandate checks below. The
     * notification goes ONLY to the recipients the actor named — D-11
     * forbids any automatic audience — and the response is the same detail
     * read every other action returns (D-14), even though this one changed
     * nothing.
     */
    public function requestUpdate(Task $task, RequestTaskUpdateData $data, User $actor): Task
    {
        $this->assertNotBlocked($task);
        $this->assertMayRequestUpdate($actor, $task);
        $this->assertRequestUpdateAvailable($task);

        $recipients = User::query()->whereIn('id', $data->recipientIds)->get();
        Notification::send($recipients, new TaskUpdateRequested($task, $actor, $data->message));

        return $this->taskService->loadDetail($task->fresh());
    }

    /**
     * The "Regola pratica" the document closes with (spec 0119, D-12), shared
     * by the two paths that CLOSE a Task: complete()'s CASO 1 and approve().
     * It reads the feedback the Task CARRIES after the write — which may come
     * from this payload or may already have been on the record — never the
     * submitted flag, since a closure can inherit a feedback nobody sent now.
     */
    private function notifyClosure(Task $task, User $actor): void
    {
        if (filled($task->closure_feedback)) {
            $this->notifier->feedbackInserted($task, $actor);

            return;
        }

        $this->notifier->closed($task, $actor);
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

    /**
     * The mirror of assertOwnsMandateForValidation()/assertOwnsMandateForBlocking()
     * for requestUpdate() (spec 0118, D-10): the one matrix row that also
     * admits the watcher, so it is NOT phrased as "owns the mandate" like
     * the other two — TaskAbilityResolver::canRequestUpdate() is its own row,
     * not a synonym for ownsTheMandate().
     */
    private function assertMayRequestUpdate(User $actor, Task $task): void
    {
        abort_unless(
            TaskAbilityResolver::canRequestUpdate($actor, $task),
            403,
            'Only the creator, the requester, a watcher or a manager may request an update on this task.',
        );
    }

    private function assertCompletable(Task $task): void
    {
        if (! $this->availability->isCompletable($task)) {
            abort(422, 'This task is already closed or awaiting validation.');
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
     * D-10: the same availability window as complete() — `isCompletable()`,
     * not a twin method — carrying its own message for this action.
     */
    private function assertRequestUpdateAvailable(Task $task): void
    {
        if (! $this->availability->isCompletable($task)) {
            abort(422, 'This task is already closed or awaiting validation.');
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
