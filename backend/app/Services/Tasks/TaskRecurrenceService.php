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
     *
     * Spec 0155, D-2: `Task::parentTask()`'s FK is `restrictOnDelete` — an
     * occurrence carrying its copied sub-tasks cannot be deleted while they
     * still exist, virgin or not. `isVirgin()` having ALREADY confirmed every
     * one of them is untouched (isUntouchedCopiedSubtask()) makes deleting
     * them first, right before the occurrence itself, safe: neither holds
     * anything a real user has acted on. Spec 0161, D-3 extends this to the
     * WHOLE copied tree: deleteSubtaskTree() walks it deepest-first, so a
     * grandchild is gone before the `restrictOnDelete` FK is asked to delete
     * its own parent.
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
                    $this->deleteSubtaskTree($occurrence);
                    $occurrence->delete();
                }
            });
    }

    /**
     * Deletes every DESCENDANT of $node (never $node itself), deepest node
     * first: $node->subtasks is walked recursively before $node's own
     * children are deleted, so a grandchild is always gone before its parent
     * — the order `restrictOnDelete` requires. $node->subtasks is assumed
     * already loaded (isVirgin()'s own TaskSubtaskTreeLoader call, right
     * before this runs), so no lazy load fires here.
     */
    private function deleteSubtaskTree(Task $node): void
    {
        foreach ($node->subtasks as $subtask) {
            $this->deleteSubtaskTree($subtask);
            $subtask->delete();
        }
    }

    /**
     * D-11, verbatim: future (filtered by the caller already), phase
     * open/pending, not blocked, no completion_date, no closure_feedback,
     * and no notes/attachments of its own — the first sign of life anywhere
     * in that list makes the occurrence NOT virgin. Spec 0155, D-2 changes
     * the sub-task leg only: an occurrence's COPIED sub-tasks no longer
     * disqualify it outright — only a copy that was itself TOUCHED does
     * (isUntouchedCopiedSubtask() below). Spec 0161, D-3 extends the check
     * to the WHOLE copied tree via subtreeUntouched(): an occurrence is
     * "intatta" only if EVERY node in it is, at any depth.
     */
    private function isVirgin(Task $occurrence): bool
    {
        TaskSubtaskTreeLoader::load($occurrence, ['taskStatus', 'timeEntries', 'notes', 'attachments']);
        $occurrence->loadMissing(['taskStatus', 'notes', 'attachments']);

        if ($occurrence->is_blocked) {
            return false;
        }

        if (! in_array($occurrence->taskStatus?->group, [TaskStatusGroup::Open, TaskStatusGroup::Pending], true)) {
            return false;
        }

        if ($occurrence->completion_date !== null || trim((string) $occurrence->closure_feedback) !== '') {
            return false;
        }

        if (! $this->subtreeUntouched($occurrence)) {
            return false;
        }

        return $occurrence->notes->isEmpty() && $occurrence->attachments->isEmpty();
    }

    /**
     * Every DIRECT sub-task of $node is untouched (isUntouchedCopiedSubtask())
     * AND its own sub-tree is too, recursively — so a touched node anywhere
     * below $node, at any depth, makes this false (spec 0161, D-3).
     */
    private function subtreeUntouched(Task $node): bool
    {
        return $node->subtasks->every(
            fn (Task $subtask): bool => $this->isUntouchedCopiedSubtask($subtask) && $this->subtreeUntouched($subtask),
        );
    }

    /**
     * Spec 0155, D-2: a sub-task TaskOccurrenceFactory copied onto an
     * occurrence counts as untouched — and so does not spoil that
     * occurrence's own virginity — when NOTHING real has happened to it
     * since: the exact same D-11 signal isVirgin() checks on the occurrence
     * itself (blocked, phase, completion/closure_feedback, notes/
     * attachments), plus its own segnatempo. Deliberately NOT a
     * `updated_at == created_at` timestamp comparison: a copy created and
     * then completed within the same wall-clock SECOND (routine in a test,
     * not impossible in production either — these columns carry no
     * microseconds) would round-trip to an identical value and hide a real
     * completion; checking the fields a touch actually WRITES has no such
     * blind spot.
     */
    private function isUntouchedCopiedSubtask(Task $subtask): bool
    {
        if ($subtask->is_blocked) {
            return false;
        }

        if (! in_array($subtask->taskStatus?->group, [TaskStatusGroup::Open, TaskStatusGroup::Pending], true)) {
            return false;
        }

        if ($subtask->completion_date !== null || trim((string) $subtask->closure_feedback) !== '') {
            return false;
        }

        return $subtask->timeEntries->isEmpty() && $subtask->notes->isEmpty() && $subtask->attachments->isEmpty();
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
