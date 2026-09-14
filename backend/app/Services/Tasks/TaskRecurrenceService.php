<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\DataObjects\Tasks\TaskRecurrenceData;
use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskRecurrence;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Writes/updates/cancels the recurrence RULE off the two Task write paths
 * (spec 0120, D-3/D-10/D-12/D-13): App\Services\TaskService::create() calls
 * set(), ::update() calls replace()/cancel() depending on the three-way
 * `recurrence` key (absent/null/object, data_contract). Never touches
 * `Task::save()` itself — every method here only prepares the model /
 * writes the `task_recurrences` row, so the caller's own save() persists
 * everything (the FK included) in ONE statement, inside ONE transaction.
 *
 * Does NOT generate occurrence Tasks (that is
 * App\Console\Commands\GenerateTaskRecurrences, via TaskOccurrenceFactory):
 * this class only owns the RULE, never the schedule it produces.
 */
final class TaskRecurrenceService
{
    /**
     * Creation path (D-3/D-4): the Task has no existing recurrence, so a
     * fresh row is always in order. Sets `task_recurrence_id` on the
     * IN-MEMORY model only — $task is not saved yet.
     */
    public function set(Task $task, TaskRecurrenceData $data): void
    {
        $this->assertEndDatePresent($task);

        $recurrence = TaskRecurrence::create($data->attributes());
        $task->task_recurrence_id = $recurrence->id;
    }

    /**
     * PATCH path, `recurrence` submitted as an object (data_contract):
     * creates a fresh series if $task has none yet, otherwise UPDATES the
     * EXISTING `task_recurrences` row in place — never delete+recreate,
     * which would `nullOnDelete` every Task already linked to it, including
     * the past ones D-10 says must stay intact.
     *
     * D-10/D-11: before the rule changes, the series' own future VIRGIN
     * occurrences (never $task itself — see pruneVirginFutureOccurrences())
     * are deleted; `generated_until` resets to null so the next
     * `tasks:generate-recurrences` run recalculates from $task's own
     * `end_date` under the NEW rule (TaskRecurrenceCalculator's own class
     * docblock: restarting from any of the series' own valid dates is safe).
     */
    public function replace(Task $task, TaskRecurrenceData $data): void
    {
        $this->assertEndDatePresent($task);

        $recurrence = $task->recurrence;

        if ($recurrence === null) {
            $recurrence = TaskRecurrence::create($data->attributes());
            $task->task_recurrence_id = $recurrence->id;

            return;
        }

        $this->pruneVirginFutureOccurrences($recurrence, exceptTaskId: $task->id);

        $recurrence->fill($data->attributes());
        $recurrence->generated_until = null;
        $recurrence->save();
    }

    /**
     * PATCH path, `recurrence` submitted as null (D-10): the series stops
     * generating and every Task already linked to it — INCLUDING $task
     * itself — is unlinked by the `nullOnDelete` FK, never deleted.
     */
    public function cancel(Task $task): void
    {
        $task->recurrence?->delete();
    }

    /**
     * D-11's exact definition, applied to every OTHER Task on the series
     * whose `end_date` is still future: deleted outright when virgin, left
     * untouched otherwise. $exceptTaskId is always the Task this very PATCH
     * is writing — it is mid-request, never a candidate for deletion by the
     * very rule change it is submitting, capostipite or not (D-4).
     */
    private function pruneVirginFutureOccurrences(TaskRecurrence $recurrence, int $exceptTaskId): void
    {
        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();

        $recurrence->tasks()
            ->where('id', '!=', $exceptTaskId)
            ->where('end_date', '>', $today)
            ->get()
            ->each(function (Task $occurrence): void {
                if ($this->isVirgin($occurrence)) {
                    $occurrence->delete();
                }
            });
    }

    /**
     * D-11, verbatim: future (filtered by the caller already), phase
     * open/pending, not blocked, no completion_date, no closure_feedback,
     * and no sub-tasks/notes/attachments — the first sign of life anywhere
     * in that list makes the occurrence NOT virgin.
     */
    private function isVirgin(Task $occurrence): bool
    {
        $occurrence->loadMissing(['taskStatus', 'subtasks', 'notes', 'attachments']);

        if ($occurrence->is_blocked) {
            return false;
        }

        if (! in_array($occurrence->taskStatus?->group, [TaskStatusGroup::Open, TaskStatusGroup::Pending], true)) {
            return false;
        }

        if ($occurrence->completion_date !== null || trim((string) $occurrence->closure_feedback) !== '') {
            return false;
        }

        return $occurrence->subtasks->isEmpty() && $occurrence->notes->isEmpty() && $occurrence->attachments->isEmpty();
    }

    /**
     * D-5: the occurrence date IS the anchor's `end_date`, so a recurrence
     * cannot be attached to a Task that does not have one. AC-027 covers
     * this at the FormRequest layer for POST (where `end_date` is already
     * unconditionally required, so this never fires there in practice); this
     * is the general, resulting-state guard that also protects PATCH, which
     * the data_contract does not special-case but which the calculator
     * depends on just the same.
     *
     * @throws ValidationException 422 on `recurrence`
     */
    private function assertEndDatePresent(Task $task): void
    {
        if ($task->end_date !== null) {
            return;
        }

        throw ValidationException::withMessages([
            'recurrence' => ['A recurrence requires the task to have an end date.'],
        ]);
    }
}
