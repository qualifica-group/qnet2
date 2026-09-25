<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use App\RichText\RichTextAttachmentCopier;
use App\Services\Notifications\TaskNotifier;
use Carbon\CarbonImmutable;

/**
 * Materializes ONE occurrence of a recurrence series off its capostipite
 * (spec 0120, D-5/D-6/D-7; spec 0155, D-2 adds the direct sub-tasks copy;
 * spec 0161, D-3 REQUIREMENT CHANGED that to the WHOLE sub-task tree, every
 * level): persists the new Task row, syncs the two user pivots and fires the
 * same two notification-map entries (voci 7/8, D-14) a client-submitted
 * create fires, with `?User $actor` null — the system, not a person, is the
 * author of a generated occurrence.
 *
 * Runs OUTSIDE any transaction of its own: App\Console\Commands\
 * GenerateTaskRecurrences wraps EACH call in its own DB::transaction()
 * (constraints: one per occurrence, never one for the whole run), the same
 * way App\Services\TaskService wraps create()/update().
 */
final class TaskOccurrenceFactory
{
    public function __construct(
        private readonly TaskInitialStatusResolver $initialStatusResolver,
        private readonly TaskNotifier $notifier,
        private readonly RichTextAttachmentCopier $descriptionCopier,
    ) {}

    public function materialize(Task $originator, CarbonImmutable $endDate): Task
    {
        $originator->loadMissing(['assignees', 'watchers', 'creator']);

        $assigneeIds = $originator->assignees->pluck('id')->all();
        $watcherIds = $originator->watchers->pluck('id')->all();

        $occurrence = new Task($this->copiedAttributes($originator, $endDate));
        $occurrence->creator_id = $originator->creator_id;
        $occurrence->task_recurrence_id = $originator->task_recurrence_id;
        // D-7: RE-DERIVED, never copied from the originator's own status.
        $occurrence->task_status_id = $this->initialStatusResolver->resolve(
            $assigneeIds,
            $originator->creator_id,
            $originator->requester_id,
        );
        $occurrence->save();

        // Spec 0128, D-8: the occurrence gets its OWN copy of every
        // `rich_text` attachment the originator's description references, its
        // `data-attachment-id`s rewritten to the new ids — only possible now
        // that $occurrence has one. The uploader on record is the same actor
        // already carried as the occurrence's creator, since a generated
        // occurrence has no human actor of its own (see class docblock).
        $occurrence->description = $this->descriptionCopier->copy(
            $originator->description,
            $originator,
            $occurrence,
            $originator->creator,
        );

        if ($occurrence->isDirty('description')) {
            $occurrence->save();
        }

        $occurrence->assignees()->sync($assigneeIds);
        $occurrence->watchers()->sync($watcherIds);

        $this->notifier->assigned($occurrence, null, $assigneeIds);
        $this->notifier->watching($occurrence, null, $watcherIds);

        $this->copySubtasks($originator, $occurrence, $endDate);

        return $occurrence;
    }

    /**
     * Spec 0161, D-3 (REQUIREMENT CHANGED from spec 0155, D-2's "un
     * livello"): the originator's ENTIRE sub-task tree, every level, is
     * copied onto the occurrence — each copy becomes the direct parent the
     * next level's own copy attaches to, so the hierarchy is reproduced
     * exactly, not flattened. TaskSubtaskTreeLoader loads the whole tree
     * first (no fixed-depth `with()` chain: a manually nested sub-task can
     * sit deeper than the bulk-create limit of 3, spec 0161 D-1, since
     * TaskHierarchyGuard itself imposes none there). No opening notification
     * fires for any copied sub-task at any depth, mirroring
     * TaskSubtaskBatchCreator's own "nessuna notifica di apertura" for a
     * bulk-created one — a generated occurrence is not an event a sub-task's
     * own assignees need paging for on its own.
     */
    private function copySubtasks(Task $originator, Task $occurrence, CarbonImmutable $endDate): void
    {
        TaskSubtaskTreeLoader::load($originator, ['assignees', 'watchers']);

        $shiftDays = $this->subtaskDateShiftDays($originator, $endDate);

        $this->copySubtaskLevel($originator, $occurrence, $shiftDays, $originator->creator_id);
    }

    /**
     * One level of the recursive copy: every DIRECT sub-task of
     * $originatorParent is copied onto $occurrenceParent, then recursed into
     * with the just-created copy as the new parent. $rootCreatorId is fixed
     * for the whole tree — the capostipite's own creator, never an
     * intermediate node's (materialize() carries no per-node actor either).
     */
    private function copySubtaskLevel(Task $originatorParent, Task $occurrenceParent, int $shiftDays, int $rootCreatorId): void
    {
        foreach ($originatorParent->subtasks as $subtask) {
            $assigneeIds = $subtask->assignees->pluck('id')->all();
            $watcherIds = $subtask->watchers->pluck('id')->all();

            $subtaskCopy = new Task($this->copiedSubtaskAttributes($subtask, $shiftDays));
            $subtaskCopy->creator_id = $rootCreatorId;
            $subtaskCopy->parent_task_id = $occurrenceParent->id;
            // D-4 of spec 0155: the manual order survives the copy
            // untouched — not mass-assignable (Task's own #[Fillable]
            // excludes it, the same category as `stage_position`), so it is
            // set directly here.
            $subtaskCopy->subtask_position = $subtask->subtask_position;
            $subtaskCopy->task_status_id = $this->initialStatusResolver->resolve(
                $assigneeIds,
                $rootCreatorId,
                $subtask->requester_id,
            );
            $subtaskCopy->save();

            $subtaskCopy->assignees()->sync($assigneeIds);
            $subtaskCopy->watchers()->sync($watcherIds);

            $this->copySubtaskLevel($subtask, $subtaskCopy, $shiftDays, $rootCreatorId);
        }
    }

    /**
     * D-5's exact day-offset rule (`shiftedStartDate()` below), generalized
     * to any pair of dates: how many days $endDate sits away from the
     * originator's own `end_date` — the SAME shift every one of its
     * sub-tasks' own dates moves by (D-2), regardless of that sub-task's own
     * `end_date` relative to the parent's.
     */
    private function subtaskDateShiftDays(Task $originator, CarbonImmutable $endDate): int
    {
        $magnitude = (int) $originator->end_date->diffInDays($endDate);

        return $endDate->gt($originator->end_date) ? $magnitude : -$magnitude;
    }

    /**
     * The same field list `copiedAttributes()` copies for a root occurrence,
     * applied one level down to a DIRECT sub-task of the originator (D-2):
     * `work_order_stage_id` is deliberately absent — a sub-task never
     * carries one (Task's own class docblock), so the constructed row's
     * default (null) already holds. `description` is copied verbatim,
     * unlike the occurrence's own (spec 0128, D-8): a sub-task's rich-text
     * attachments are out of this spec's scope (D-2 lists plain fields only).
     *
     * @return array<string, mixed>
     */
    private function copiedSubtaskAttributes(Task $subtask, int $shiftDays): array
    {
        return [
            'title' => $subtask->title,
            'description' => $subtask->description,
            'registry_id' => $subtask->registry_id,
            'referent_id' => $subtask->referent_id,
            'task_type_id' => $subtask->task_type_id,
            'task_priority_id' => $subtask->task_priority_id,
            'task_importance_id' => $subtask->task_importance_id,
            'task_category_id' => $subtask->task_category_id,
            'opportunity_id' => $subtask->opportunity_id,
            'work_order_id' => $subtask->work_order_id,
            'requester_id' => $subtask->requester_id,
            'start_date' => $subtask->start_date?->addDays($shiftDays)->toDateString(),
            'end_date' => $subtask->end_date?->addDays($shiftDays)->toDateString(),
            'completion_date' => null,
            'start_time' => $subtask->start_time,
            'end_time' => $subtask->end_time,
            'estimated_minutes' => $subtask->estimated_minutes,
            'is_blocked' => false,
            'requires_closure_feedback' => $subtask->requires_closure_feedback,
            'requires_validation' => $subtask->requires_validation,
            'closure_feedback' => null,
        ];
    }

    /**
     * D-6's copy list, plus the D-5 date handling and the fields explicitly
     * NOT copied (closure_feedback/completion_date/is_blocked/parent_task_id
     * reset to their neutral value; task_status_id is set separately above).
     * `description` is ALSO absent (spec 0128, D-8): its `rich_text`
     * attachments must be copied onto the OCCURRENCE, so it is set via
     * RichTextAttachmentCopier once $occurrence has an id, never copied
     * verbatim here.
     *
     * @return array<string, mixed>
     */
    private function copiedAttributes(Task $originator, CarbonImmutable $endDate): array
    {
        return [
            'title' => $originator->title,
            'registry_id' => $originator->registry_id,
            'referent_id' => $originator->referent_id,
            'task_type_id' => $originator->task_type_id,
            'task_priority_id' => $originator->task_priority_id,
            'task_importance_id' => $originator->task_importance_id,
            'task_category_id' => $originator->task_category_id,
            'opportunity_id' => $originator->opportunity_id,
            'work_order_id' => $originator->work_order_id,
            'requester_id' => $originator->requester_id,
            'start_date' => $this->shiftedStartDate($originator, $endDate),
            'end_date' => $endDate->toDateString(),
            'completion_date' => null,
            'start_time' => $originator->start_time,
            'end_time' => $originator->end_time,
            'estimated_minutes' => $originator->estimated_minutes,
            'is_blocked' => false,
            'requires_closure_feedback' => $originator->requires_closure_feedback,
            'requires_validation' => $originator->requires_validation,
            'closure_feedback' => null,
            'parent_task_id' => null,
        ];
    }

    /**
     * D-5: the occurrence's `start_date` keeps the SAME day-offset
     * (`start_date - end_date`) the originator had; absent entirely when the
     * originator has none of its own.
     */
    private function shiftedStartDate(Task $originator, CarbonImmutable $endDate): ?string
    {
        if ($originator->start_date === null || $originator->end_date === null) {
            return null;
        }

        $magnitude = (int) $originator->start_date->diffInDays($originator->end_date);
        $offsetDays = $originator->start_date->gt($originator->end_date) ? $magnitude : -$magnitude;

        return $endDate->addDays($offsetDays)->toDateString();
    }
}
