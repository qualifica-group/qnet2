<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\DataObjects\TimeEntries\TimeEntryData;
use App\Enums\TaskStatusSystemKey;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\Notifications\TaskNotifier;
use App\Services\TimeEntries\TimeEntryService;
use Illuminate\Validation\ValidationException;

/**
 * D-6 of spec 0154: `is_completed: true` on POST /api/tasks bypasses the
 * normal open/assigned birth entirely — the Task is born ALREADY in the
 * `closed_positive` system status, `completion_date` today, with one
 * segnatempo of the actor. Split out of App\Services\TaskService (file size,
 * engineering.md §6): this class owns the "born closed" write and its own
 * notification, TaskService only decides WHETHER to call it.
 *
 * `requires_validation` is an outright 422 here (never silently downgraded
 * to the closure percorso): a Task flagged for validation is asking for a
 * second pair of eyes before it closes, which "born already closed" would
 * skip entirely — the same veto `TaskValidationRequirementGuard` applies to
 * a PATCH that tries to walk straight into a closing status.
 *
 * The segnatempo's `minutes` floors at 1 even when `estimated_minutes` is
 * null or 0 (D-6 literally allows 0, "anche 0"): TimeEntryValidationRules
 * enforces `minutes: min:1` on every OTHER write path to a TimeEntry, and
 * this one bypasses that FormRequest by construction (there is no HTTP
 * payload for the segnatempo here, TaskService builds it straight off the
 * Task) — floors rather than admits 0 so a "born completed" Task never
 * produces the one TimeEntry row in the system that would violate an
 * invariant every report/aggregate elsewhere assumes.
 */
final class TaskCreationCompletion
{
    private const string NO_TASK_TYPE_MESSAGE = 'A task type is required to create an already completed task.';

    private const string REQUIRES_VALIDATION_MESSAGE = 'A task that requires validation cannot be created already completed.';

    public function __construct(
        private readonly TaskClosureFeedbackGuard $closureFeedbackGuard,
        private readonly TaskNotifier $notifier,
        private readonly TimeEntryService $timeEntryService,
    ) {}

    /**
     * Overwrites $task's status/completion_date, asserts the closing-feedback
     * rule on the resulting state and logs the segnatempo — all BEFORE the
     * caller's own save()/sync() of the two user pivots, so
     * `notifyClosure()`'s "more than one assignee" read (D-11 of spec 0153)
     * sees the final pivot. Call inside the same transaction as the rest of
     * TaskService::create().
     *
     * @throws ValidationException 422 on `requires_validation`/`task_type_id`
     */
    public function apply(Task $task, User $actor): void
    {
        if ($task->requires_validation) {
            throw ValidationException::withMessages(['requires_validation' => [self::REQUIRES_VALIDATION_MESSAGE]]);
        }

        if ($task->task_type_id === null) {
            throw ValidationException::withMessages(['task_type_id' => [self::NO_TASK_TYPE_MESSAGE]]);
        }

        $task->task_status_id = $this->closedPositiveStatusId();
        $task->completion_date = now()->toDateString();

        $this->closureFeedbackGuard->assertSatisfied($task);
        $task->save();

        $this->timeEntryService->create($this->timeEntryData($task), $actor);
    }

    /**
     * D-7 (spec 0154): the ONE notification a "born completed" Task ever
     * sends — reusing the D-11 closure audience of spec 0153 (requester +
     * watchers, assignees only when more than one) rather than the ordinary
     * voce 7/8 assignment pair, since a Task that is already finished was
     * never really "assigned" in the open sense those two notify about.
     */
    public function notifyClosure(Task $task, User $actor): void
    {
        $includeAssignees = $task->assignees()->count() > 1;

        if (filled($task->closure_feedback)) {
            $this->notifier->feedbackInserted($task, $actor, $includeAssignees);

            return;
        }

        $this->notifier->closed($task, $actor, $includeAssignees);
    }

    private function timeEntryData(Task $task): TimeEntryData
    {
        return TimeEntryData::forTask([
            'date' => now()->toDateString(),
            'task_type_id' => $task->task_type_id,
            'minutes' => max((int) ($task->estimated_minutes ?? 0), 1),
        ], (int) $task->id);
    }

    private function closedPositiveStatusId(): int
    {
        return (int) TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedPositive->value)->value('id');
    }
}
