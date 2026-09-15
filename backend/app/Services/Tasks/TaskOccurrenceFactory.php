<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use App\RichText\RichTextAttachmentCopier;
use App\Services\Notifications\TaskNotifier;
use Carbon\CarbonImmutable;

/**
 * Materializes ONE occurrence of a recurrence series off its capostipite
 * (spec 0120, D-5/D-6/D-7): persists the new Task row, syncs the two user
 * pivots and fires the same two notification-map entries (voci 7/8, D-14) a
 * client-submitted create fires, with `?User $actor` null — the system, not
 * a person, is the author of a generated occurrence.
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

        return $occurrence;
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
