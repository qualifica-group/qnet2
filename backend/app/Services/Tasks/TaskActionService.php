<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\DataObjects\Tasks\RequestTaskUpdateData;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskUpdateRequested;
use App\Services\Notifications\TaskNotifier;
use App\Services\TaskService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The domain actions that do NOT move a Task's completion phase (spec 0116,
 * D-8; spec 0118, D-10..D-14): block, unblock, requestUpdate. The completion
 * lifecycle (complete, uncomplete, approve, reject) lives in
 * TaskCompletionService. Every method ends with the SAME detail read
 * TaskController::show uses (TaskService::loadDetail()), so every action's
 * response is identical in shape to a plain GET.
 *
 * Every method re-asserts, on top of the controller's `$this->authorize()`,
 * exactly what App\Authorization\TasksAuthorization::actionPermissions()
 * computes for the UI flag: the record-role matrix (TaskAbilityResolver),
 * because `Gate::before` for the privileged role bypasses the Policy
 * entirely (AC-011, AC-042), and the injected TaskActionAvailability for
 * WHEN the action makes sense (422), never mixed with authorization (D-1).
 *
 * Notifications (spec 0119, D-12) are one TaskNotifier call per write path,
 * inside the transaction (the send is deferred to `DB::afterCommit()`,
 * AC-028). requestUpdate() keeps its own spec 0118 notification, sent to the
 * caller-chosen recipients alone.
 */
final class TaskActionService
{
    public function __construct(
        private readonly TaskService $taskService,
        private readonly TaskActionAvailability $availability,
        private readonly TaskNotifier $notifier,
    ) {}

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
     * transaction — there is nothing to write to the Task and therefore
     * nothing to roll back. Availability re-uses `isCompletable()`, the SAME
     * rule complete() enforces (D-10 forbids a twin method). The notification
     * goes ONLY to the recipients the actor named (D-11), and the response is
     * the same detail read every other action returns (D-14).
     *
     * Spec 0126, D-6 (REQUIREMENT CHANGED): carries no `TaskWriteLock::
     * assertNotBlocked()` of its own, unlike block/unblock's siblings in
     * `TaskCompletionService` — a blocked Task now admits this one action.
     */
    public function requestUpdate(Task $task, RequestTaskUpdateData $data, User $actor): Task
    {
        $this->assertMayRequestUpdate($actor, $task);
        $this->assertRequestUpdateAvailable($task);

        $recipients = User::query()->whereIn('id', $data->recipientIds)->get();
        Notification::send($recipients, new TaskUpdateRequested($task, $actor, $data->message));

        return $this->taskService->loadDetail($task->fresh());
    }

    /**
     * D-2, D-8: "bloccare" in the document's deroga covers both directions,
     * both gated by the same `tasks.block` ability and matrix row.
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
     * Spec 0118, D-10: the one matrix row that also admits the watcher, so it
     * is NOT phrased as "owns the mandate" —
     * TaskAbilityResolver::canRequestUpdate() is its own row.
     */
    private function assertMayRequestUpdate(User $actor, Task $task): void
    {
        abort_unless(
            TaskAbilityResolver::canRequestUpdate($actor, $task),
            403,
            'Only the creator, the requester, a watcher or a manager may request an update on this task.',
        );
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
}
