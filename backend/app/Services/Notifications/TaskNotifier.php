<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskClosed;
use App\Notifications\TaskFeedbackInserted;
use App\Notifications\TaskLocked;
use App\Notifications\TaskNotification;
use App\Notifications\TaskObserver;
use App\Notifications\TaskUnCompleted;
use App\Notifications\TaskUnLocked;
use App\Notifications\TaskValidationApproved;
use App\Notifications\TaskValidationRejected;
use App\Notifications\TaskValidationReopened;
use App\Notifications\TaskValidationRequested;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The ONE place that turns a Task transition into notifications (spec 0119,
 * D-2). Eleven public methods, one per row of the Mappa Notifiche in
 * DOC Tasks.docx, so a write path says WHAT happened and never WHO should
 * hear about it.
 *
 * Modelled on AssignmentNotifier, down to the shape: the audience is resolved
 * first, the recipients are loaded in ONE query, and the send happens inside
 * DB::afterCommit(). That last part is not a detail — six of the seven write
 * paths that call this class run inside a transaction, and a notification
 * spilled out of one that later rolls back is irrecoverable.
 *
 * Deliberately NOT an observer and not an event/listener pair: this repo has
 * no model observers or domain events (constraint carried since spec 0077,
 * restated by D-10), and the transitions reported here are explicit service
 * calls, not Eloquent writes an observer could see.
 *
 * The WHO lives entirely in TaskNotificationAudience; the WHAT lives entirely
 * in the eleven notification classes. This class only wires the two together,
 * which is why every method below is three lines long.
 */
final class TaskNotifier
{
    /**
     * Voce 1. The requester is asked to validate; D-5 lets the creator stand
     * in when there is no requester, since here they are the sole recipient.
     */
    public function validationRequested(Task $task, ?User $actor): void
    {
        $this->send($task, $this->audience($task)->requesterOrCreator($actor), TaskValidationRequested::class, $actor);
    }

    /** Voce 2. Closed with no closing feedback. */
    public function closed(Task $task, ?User $actor): void
    {
        $this->send($task, $this->audience($task)->everyone($actor), TaskClosed::class, $actor);
    }

    /** Voce 3. Closed carrying a closing feedback. */
    public function feedbackInserted(Task $task, ?User $actor): void
    {
        $this->send($task, $this->audience($task)->everyone($actor), TaskFeedbackInserted::class, $actor);
    }

    /** Voce 4, which doubles as the document's "email di conferma". */
    public function validationApproved(Task $task, ?User $actor): void
    {
        $this->send($task, $this->audience($task)->assignees($actor), TaskValidationApproved::class, $actor);
    }

    /** Voce 5. */
    public function validationRejected(Task $task, ?User $actor): void
    {
        $this->send($task, $this->audience($task)->assignees($actor), TaskValidationRejected::class, $actor);
    }

    /** Voce 6. Withdrawn from validation by the assignee who sent it there. */
    public function validationReopened(Task $task, ?User $actor): void
    {
        $this->send($task, $this->audience($task)->requesterAndCreator($actor), TaskValidationReopened::class, $actor);
    }

    /**
     * Voce 7.
     *
     * @param  ?array<int, int>  $onlyUserIds  D-9: on a PATCH the audience is
     *                                         not "the assignees" but "the
     *                                         assignees just ADDED", which
     *                                         the caller computes from the
     *                                         pivot delta. Null (a creation)
     *                                         means every assignee.
     */
    public function assigned(Task $task, ?User $actor, ?array $onlyUserIds = null): void
    {
        $recipients = $onlyUserIds === null
            ? $this->audience($task)->assignees($actor)
            : TaskNotificationAudience::restrict($onlyUserIds, $actor);

        $this->send($task, $recipients, TaskAssigned::class, $actor);
    }

    /**
     * Voce 8, the watcher twin of assigned().
     *
     * @param  ?array<int, int>  $onlyUserIds  see assigned().
     */
    public function watching(Task $task, ?User $actor, ?array $onlyUserIds = null): void
    {
        $recipients = $onlyUserIds === null
            ? $this->audience($task)->watchers($actor)
            : TaskNotificationAudience::restrict($onlyUserIds, $actor);

        $this->send($task, $recipients, TaskObserver::class, $actor);
    }

    /** Voce 9. Reopened from a closing phase, not from validation. */
    public function uncompleted(Task $task, ?User $actor): void
    {
        $this->send($task, $this->audience($task)->everyone($actor), TaskUnCompleted::class, $actor);
    }

    /** Voce 10. */
    public function locked(Task $task, ?User $actor): void
    {
        $this->send($task, $this->audience($task)->everyone($actor), TaskLocked::class, $actor);
    }

    /** Voce 11. */
    public function unlocked(Task $task, ?User $actor): void
    {
        $this->send($task, $this->audience($task)->everyone($actor), TaskUnLocked::class, $actor);
    }

    /**
     * The two pivots must be present before the audience is read:
     * `Model::preventLazyLoading()` is on outside production, so a missing
     * eager load fails loudly rather than degrading into N+1.
     */
    private function audience(Task $task): TaskNotificationAudience
    {
        return TaskNotificationAudience::of($task->loadMissing(['assignees', 'watchers']));
    }

    /**
     * The shared tail of all eleven.
     *
     * Step 1 skips the work entirely when nobody is left to tell — which is
     * the normal outcome whenever the actor was the only member (D-3).
     * Step 2 defers to after the commit. Step 3 loads every recipient in ONE
     * query keyed by id, so a stale or deleted id resolves to null at send
     * time instead of throwing (AC-011).
     *
     * @param  array<int, int>  $recipientIds
     * @param  class-string<TaskNotification>  $notification
     */
    private function send(Task $task, array $recipientIds, string $notification, ?User $actor): void
    {
        // Step 1: nothing to do.
        if ($recipientIds === []) {
            return;
        }

        // Step 2: never from inside the transaction that is still open.
        DB::afterCommit(function () use ($task, $recipientIds, $notification, $actor): void {
            // Step 3: every recipient in one query.
            $recipients = User::query()->whereIn('id', $recipientIds)->get();

            if ($recipients->isEmpty()) {
                return;
            }

            Notification::send($recipients, new $notification($task, $actor));
        });
    }
}
