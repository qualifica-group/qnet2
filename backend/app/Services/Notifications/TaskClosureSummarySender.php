<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\TaskNotification;
use App\Notifications\TasksClosedSummaryNotification;
use App\Support\Notifications\RecordDetails;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/**
 * Sends the closures one operation collected (spec 0428, via
 * TaskNotifier::summarizingClosures()), grouped by RECIPIENT: whoever hears
 * about one Task only gets the per-task notification of always (D-4), whoever
 * hears about two or more gets ONE TasksClosedSummaryNotification. The
 * audience of every Task is the one TaskNotifier already resolved (D-3): this
 * class only regroups it.
 *
 * @phpstan-type PendingClosure array{task: Task, actor: ?User, recipientIds: array<int, int>, notification: class-string<TaskNotification>}
 */
final class TaskClosureSummarySender
{
    /**
     * @param  list<PendingClosure>  $closures
     */
    public function send(array $closures): void
    {
        // Step 1: recipient id => the closures they hear about.
        $byRecipient = $this->groupByRecipient($closures);

        if ($byRecipient === []) {
            return;
        }

        // Step 2: never from inside a transaction still open (D-5).
        DB::afterCommit(function () use ($byRecipient): void {
            // Step 3: every recipient in one query, disabled users dropped
            // like the per-task closure notifications drop them.
            $recipients = User::query()->whereIn('id', array_keys($byRecipient))->where('is_active', true)->get();

            foreach ($recipients as $recipient) {
                $recipient->notify($this->notificationFor($byRecipient[$recipient->id]));
            }
        });
    }

    /**
     * @param  list<PendingClosure>  $closures
     * @return array<int, list<PendingClosure>>
     */
    private function groupByRecipient(array $closures): array
    {
        $byRecipient = [];

        foreach ($closures as $closure) {
            foreach ($closure['recipientIds'] as $recipientId) {
                $byRecipient[$recipientId][] = $closure;
            }
        }

        return $byRecipient;
    }

    /**
     * @param  list<PendingClosure>  $closures  at least one
     */
    private function notificationFor(array $closures): Notification
    {
        if (count($closures) === 1) {
            $closure = $closures[0];

            return new $closure['notification']($closure['task'], $closure['actor']);
        }

        $tasks = array_column($closures, 'task');
        $workOrder = $this->sharedWorkOrder($tasks);

        return new TasksClosedSummaryNotification(
            taskTitles: array_map(fn (Task $task): string => (string) $task->title, $tasks),
            actorName: $closures[0]['actor']?->name,
            workOrderId: $workOrder?->id,
            workOrderLabel: $workOrder === null ? null : RecordDetails::labelFor($workOrder),
            workOrderDetails: $workOrder === null ? [] : RecordDetails::for($workOrder),
        );
    }

    /**
     * The commessa every Task belongs to, or null when they are spread over
     * several or belong to none.
     *
     * @param  array<int, Task>  $tasks
     */
    private function sharedWorkOrder(array $tasks): ?WorkOrder
    {
        $workOrderIds = array_unique(array_map(fn (Task $task): ?int => $task->work_order_id, $tasks));

        if (count($workOrderIds) !== 1 || $workOrderIds[array_key_first($workOrderIds)] === null) {
            return null;
        }

        return WorkOrder::query()->find($workOrderIds[array_key_first($workOrderIds)]);
    }
}
