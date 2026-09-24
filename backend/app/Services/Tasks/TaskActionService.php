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
 * AC-028). requestUpdate() keeps its own notification (spec 0153, D-14): sent
 * to the fixed `target` group resolved off the Task's current pivots, never
 * via TaskNotifier/TaskNotificationAudience.
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
     * "Richiedi aggiornamento" (spec 0153, D-14, superseding spec 0118
     * D-10..D-14 and spec 0126 D-6): does NOT open a transaction — there is
     * nothing to write to the Task and therefore nothing to roll back.
     * Availability re-uses `isCompletable()`, the SAME rule complete()
     * enforces (D-10 forbids a twin method), PLUS its own "not blocked" 422
     * (D-14 REVERSES spec 0126 D-6's exemption). Recipients are the fixed
     * `target` group resolved off the Task's OWN current pivots, never the
     * actor-picked list of the old contract; the actor is never excluded.
     */
    public function requestUpdate(Task $task, RequestTaskUpdateData $data, User $actor): Task
    {
        $this->assertMayRequestUpdate($actor, $task);
        $this->assertRequestUpdateAvailable($task);

        $task->loadMissing(['assignees', 'watchers']);
        [$recipientIds, $ccIds] = $this->resolveRequestUpdateRecipients($task, $data->target);

        $this->sendRequestUpdate($task, $actor, $data->message, $recipientIds, isCc: false);
        $this->sendRequestUpdate($task, $actor, $data->message, $ccIds, isCc: true);

        return $this->taskService->loadDetail($task->fresh());
    }

    /**
     * D-14's three fixed groups: `assignees` carries every watcher NOT
     * already an assignee in copy (`is_cc: true`, a SEPARATE notification);
     * `observers` and `all` send no copy — `all` already reaches everyone in
     * ONE direct list.
     *
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    private function resolveRequestUpdateRecipients(Task $task, string $target): array
    {
        $assigneeIds = $task->assignees->pluck('id')->map(intval(...))->all();
        $watcherIds = $task->watchers->pluck('id')->map(intval(...))->all();

        return match ($target) {
            'observers' => [$watcherIds, []],
            'all' => [array_values(array_unique([...$assigneeIds, ...$watcherIds])), []],
            default => [$assigneeIds, array_values(array_diff($watcherIds, $assigneeIds))], // 'assignees'
        };
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function sendRequestUpdate(Task $task, User $actor, string $message, array $userIds, bool $isCc): void
    {
        if ($userIds === []) {
            return;
        }

        $recipients = User::query()->whereIn('id', $userIds)->get();
        Notification::send($recipients, new TaskUpdateRequested($task, $actor, $message, $isCc));
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

    /**
     * D-7 (spec 0153, REQUIREMENT CHANGED): isBlockable() now also refuses a
     * closed/in-validation Task, not only an already-blocked one — the
     * message distinguishes the two so a closed Task is never told it is
     * "already blocked".
     */
    private function assertBlockable(Task $task): void
    {
        if ($this->availability->isBlockable($task)) {
            return;
        }

        abort(422, $task->is_blocked
            ? 'This task is already blocked.'
            : 'This task is already closed or awaiting validation.');
    }

    private function assertUnblockable(Task $task): void
    {
        if (! $this->availability->isUnblockable($task)) {
            abort(422, 'This task is not blocked.');
        }
    }

    /**
     * D-10 (spec 0118): the same availability window as complete() —
     * `isCompletable()`, not a twin method — carrying its own message for
     * this action. Spec 0153, D-14 REVERSES spec 0126 D-6's exemption: a
     * blocked Task now 422s here too, re-asserted directly on the column
     * (never `TaskWriteLock::assertNotBlocked()`, which answers 409 — this
     * endpoint's own contract is 422 for every refusal but the 403 matrix).
     */
    private function assertRequestUpdateAvailable(Task $task): void
    {
        if ($task->is_blocked) {
            abort(422, 'This task is blocked.');
        }

        if (! $this->availability->isCompletable($task)) {
            abort(422, 'This task is already closed or awaiting validation.');
        }
    }
}
