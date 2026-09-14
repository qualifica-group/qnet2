<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Parent/child date coherence (spec 0123, D-7/D-8): a Task with a parent
 * keeps every non-null `start_date`/`end_date` inside [parent.start_date,
 * parent.end_date] — a null parent extreme leaves that side unconstrained.
 * Two independent directions, both evaluated on the RESULTING state inside
 * the write transaction (AC-032 of spec 0101), never on the submitted
 * payload alone:
 *
 * - assertChildWithinParent(): the CHILD side (D-7). TaskService::create()
 *   calls it unconditionally whenever the new row has a parent (a create is
 *   never partial); TaskService::update() calls it only when
 *   `parent_task_id`, `start_date` or `end_date` was actually submitted, so
 *   a PATCH untouched on all three never re-judges a row that predates the
 *   rule.
 * - assertChildrenWithinRange(): the PARENT side (D-8, decision utente): a
 *   PATCH that leaves `start_date`/`end_date` dirty is refused if it would
 *   push at least one DIRECT child (ignoring visibility, same convention as
 *   TaskService::delete()'s own sub-task guard) outside the new range. The
 *   error lands on whichever of the two columns the client actually
 *   changed, never on a column the PATCH left alone (AC-028).
 */
final class TaskParentDateRangeGuard
{
    private const string CHILD_MESSAGE = "The date must fall within the parent task's dates.";

    /**
     * @throws ValidationException 422 on `start_date`/`end_date`
     */
    public function assertChildWithinParent(Task $task): void
    {
        if ($task->parent_task_id === null) {
            return;
        }

        $parent = Task::query()->find($task->parent_task_id);

        if ($parent === null) {
            return;
        }

        $violations = [];

        foreach (['start_date', 'end_date'] as $column) {
            $value = $task->{$column};

            if ($value === null) {
                continue;
            }

            $belowFloor = $parent->start_date !== null && $value->lt($parent->start_date);
            $aboveCeiling = $parent->end_date !== null && $value->gt($parent->end_date);

            if ($belowFloor || $aboveCeiling) {
                $violations[$column] = [self::CHILD_MESSAGE];
            }
        }

        if ($violations !== []) {
            throw ValidationException::withMessages($violations);
        }
    }

    /**
     * @throws ValidationException 422 on the dirty date column(s)
     */
    public function assertChildrenWithinRange(Task $task): void
    {
        $dirtyDateKeys = array_values(array_intersect(array_keys($task->getDirty()), ['start_date', 'end_date']));

        if ($dirtyDateKeys === [] || ($task->start_date === null && $task->end_date === null)) {
            return;
        }

        $outOfRangeCount = $this->outOfRangeChildrenQuery($task)->count();

        if ($outOfRangeCount === 0) {
            return;
        }

        $message = sprintf('%d sub-task(s) would fall outside these dates.', $outOfRangeCount);

        throw ValidationException::withMessages(array_fill_keys($dirtyDateKeys, [$message]));
    }

    /**
     * Direct children whose own `start_date`/`end_date` would land outside
     * $task's RESULTING range on at least one non-null side — the same
     * per-column rule as assertChildWithinParent(), read from the parent
     * looking down instead of from the child looking up.
     */
    private function outOfRangeChildrenQuery(Task $task): Builder
    {
        return Task::query()
            ->where('parent_task_id', $task->id)
            ->where(function (Builder $query) use ($task): void {
                if ($task->start_date !== null) {
                    $floor = $task->start_date->toDateString();
                    $query->orWhere(fn (Builder $q) => $q->whereNotNull('start_date')->where('start_date', '<', $floor));
                    $query->orWhere(fn (Builder $q) => $q->whereNotNull('end_date')->where('end_date', '<', $floor));
                }

                if ($task->end_date !== null) {
                    $ceiling = $task->end_date->toDateString();
                    $query->orWhere(fn (Builder $q) => $q->whereNotNull('start_date')->where('start_date', '>', $ceiling));
                    $query->orWhere(fn (Builder $q) => $q->whereNotNull('end_date')->where('end_date', '>', $ceiling));
                }
            });
    }
}
