<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use App\Models\WorkOrder;
use App\RichText\RichText;
use App\RichText\RichTextAttachmentCopier;
use App\Services\AttachmentService;
use App\Services\Notifications\TaskNotifier;
use App\Services\Tasks\TaskInitialStatusResolver;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Stamps a whole Modello di Task onto a just-created Commessa, one generated
 * Task per template row, in `sort_order` (spec 0124, D-6/D-7). Modelled on
 * App\Services\Tasks\TaskOccurrenceFactory: builds each `Task` directly
 * (`creator_id` assigned by property, not mass assignment) and never routes
 * through `TaskService::create()` — that Service belongs to the parallel,
 * uncommitted build of spec 0123.
 *
 * Runs INSIDE the caller's own transaction (WorkOrderService::create()'s
 * Step 4), never opening one of its own: a failure anywhere in generate() —
 * including a failed attachment copy — must roll back the whole Commessa
 * (AC-019), which only the OUTER transaction can do. The one thing this
 * class must still undo itself on failure is the copied attachment BINARIES
 * (DB::rollBack() does not touch the filesystem): every path
 * AttachmentService::copyTo() has already written is tracked and deleted
 * before the exception is rethrown.
 *
 * Spec 0146, D-2: the template's own `stages` are copied onto the commessa
 * as `work_order_stages`, same order, BEFORE any Task is generated — each
 * row below then resolves straight to the copied stage's id and its own
 * `stage_position`, progressive WITHIN that stage (or "Senza fase"), in the
 * order the template's items are read.
 */
final class WorkOrderTaskGenerator
{
    public function __construct(
        private readonly TaskInitialStatusResolver $initialStatusResolver,
        private readonly AttachmentService $attachments,
        private readonly TaskNotifier $notifier,
        private readonly RichTextAttachmentCopier $descriptionCopier,
    ) {}

    /**
     * @return array<int, Task>
     */
    public function generate(WorkOrder $workOrder, TaskTemplate $template, User $actor): array
    {
        $template->loadMissing(['items.attachments', 'items.taskStatus', 'stages']);
        $workOrder->loadMissing('quote.opportunity');
        $supervisorIds = $workOrder->supervisors()->pluck('users.id')->all();

        $stageIdsByTemplateStageId = $this->copyStages($workOrder, $template);
        /** @var array<int, int> $stagePositions keyed by copied work_order_stage_id, 0 for "Senza fase" */
        $stagePositions = [];

        /** @var array<int, array{disk: string, path: string}> $copiedFiles */
        $copiedFiles = [];
        $tasks = [];

        try {
            // A plain loop, deliberately NOT items->map(fn (...) => ...):
            // an arrow function captures $copiedFiles BY VALUE, so
            // materialize()'s by-reference parameter would mutate a throwaway
            // copy and every copied path would be lost the moment the
            // closure returns — silently defeating the rollback below.
            foreach ($template->items as $item) {
                $tasks[] = $this->materialize(
                    $item,
                    $workOrder,
                    $actor,
                    $supervisorIds,
                    $copiedFiles,
                    $stageIdsByTemplateStageId,
                    $stagePositions,
                );
            }
        } catch (Throwable $exception) {
            $this->rollbackCopiedFiles($copiedFiles);

            throw $exception;
        }

        // Notifications only after the OUTER transaction (WorkOrderService::
        // create()) commits: TaskNotifier::assigned() already defers to
        // DB::afterCommit() internally, so calling it here — still inside
        // the transaction — is correct and matches TaskOccurrenceFactory's
        // own usage.
        foreach ($tasks as $task) {
            $this->notifier->assigned($task, $actor, $supervisorIds);
        }

        return $tasks;
    }

    /**
     * One generated Task from one template row: copy the snapshot fields
     * (`generated_task` shape), resolve the status (D-4), the stage and its
     * position (spec 0146, D-2), sync assignees (D-2) and copy the row's own
     * attachments (D-6).
     *
     * @param  array<int, int>  $supervisorIds
     * @param  array<int, array{disk: string, path: string}>  $copiedFiles
     * @param  array<int, int>  $stageIdsByTemplateStageId
     * @param  array<int, int>  $stagePositions
     */
    private function materialize(
        TaskTemplateItem $item,
        WorkOrder $workOrder,
        User $actor,
        array $supervisorIds,
        array &$copiedFiles,
        array $stageIdsByTemplateStageId,
        array &$stagePositions,
    ): Task {
        // `start_date` casts to a MUTABLE Carbon (no immutable-date override
        // in this codebase): addDays() below must run on a copy, or the
        // second row generated off the same $workOrder would see its start
        // date already shifted by the first row's own offset.
        $startDate = $workOrder->start_date;
        $endDate = $startDate->copy()->addDays($item->due_offset_days);

        $workOrderStageId = $item->task_template_stage_id === null
            ? null
            : $stageIdsByTemplateStageId[$item->task_template_stage_id];
        // "Senza fase" (null) is its own group too — 0 is a safe sentinel
        // key: real WorkOrderStage ids start at 1.
        $positionGroup = $workOrderStageId ?? 0;
        $stagePosition = $stagePositions[$positionGroup] ?? 0;
        $stagePositions[$positionGroup] = $stagePosition + 1;

        $task = new Task([
            'title' => $item->title,
            'estimated_minutes' => $item->estimated_minutes,
            'work_order_id' => $workOrder->id,
            'work_order_stage_id' => $workOrderStageId,
            'registry_id' => $workOrder->quote->opportunity->registry_id,
            'opportunity_id' => $workOrder->quote->opportunity_id,
            'requester_id' => $actor->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'requires_validation' => false,
        ]);
        $task->creator_id = $actor->id;
        $task->task_status_id = $this->resolveInitialStatus($item, $supervisorIds, $actor);
        $task->stage_position = $stagePosition;
        $task->save();

        $task->assignees()->sync($supervisorIds);

        // Spec 0128, D-6/D-8: the row's `rich_text` images belong to the
        // description, copied below via RichTextAttachmentCopier (single
        // source of copy+rewrite) — skipped here to avoid copying them TWICE
        // into an unrelated 'documents' collection on the generated Task.
        foreach ($item->attachments as $attachment) {
            if ($attachment->collection === RichText::ATTACHMENT_COLLECTION) {
                continue;
            }

            $copy = $this->attachments->copyTo($attachment, $task, 'documents', $actor);
            $copiedFiles[] = ['disk' => $copy->disk, 'path' => $copy->path];
        }

        $task->description = $this->descriptionCopier->copy($item->description, $item, $task, $actor);

        if ($task->isDirty('description')) {
            $task->save();
        }

        // Track the description's own copied binaries too, so a LATER
        // failure elsewhere in generate() still rolls every file this Task
        // pulled in back off disk (rollbackCopiedFiles(), same as 'documents').
        foreach ($task->attachments()->where('collection', RichText::ATTACHMENT_COLLECTION)->get() as $copy) {
            $copiedFiles[] = ['disk' => $copy->disk, 'path' => $copy->path];
        }

        return $task;
    }

    /**
     * Copies a template's own stages onto the commessa, same order (spec
     * 0146, D-2) — BEFORE any Task is generated, so materialize() can
     * resolve every row straight to the copied WorkOrderStage id. An
     * unstaged template (`stages` empty) copies nothing, every generated
     * Task lands in "Senza fase".
     *
     * @return array<int, int> template TaskTemplateStage id => copied
     *                         WorkOrderStage id
     */
    private function copyStages(WorkOrder $workOrder, TaskTemplate $template): array
    {
        $stageIdsByTemplateStageId = [];

        foreach ($template->stages as $index => $stage) {
            $copy = $workOrder->stages()->create(['name' => $stage->name, 'sort_order' => $index]);
            $stageIdsByTemplateStageId[$stage->id] = $copy->id;
        }

        return $stageIdsByTemplateStageId;
    }

    /**
     * D-4: the row's own status wins only while it is STILL active and in
     * an open/pending phase at generation time — otherwise (unset,
     * deactivated, or moved to a different phase since the row was
     * configured) the ordinary derivation applies, silently, no error.
     *
     * @param  array<int, int>  $supervisorIds
     */
    private function resolveInitialStatus(TaskTemplateItem $item, array $supervisorIds, User $actor): int
    {
        $status = $item->taskStatus;

        if ($status !== null && $status->is_active && in_array($status->group, [TaskStatusGroup::Open, TaskStatusGroup::Pending], true)) {
            return $status->id;
        }

        return $this->initialStatusResolver->resolve($supervisorIds, $actor->id, $actor->id);
    }

    /**
     * The DB rows this generator inserted (Task, its pivot, the copied
     * `attachments` rows) are undone by the caller's own transaction
     * rollback (AC-019) — only the copied BINARIES are not, since the
     * filesystem knows nothing about SQL transactions. Removed directly by
     * disk/path, never through AttachmentService::delete(): that method
     * deletes a DB row too, which would be redundant work on a row about to
     * vanish with the rollback anyway.
     *
     * @param  array<int, array{disk: string, path: string}>  $copiedFiles
     */
    private function rollbackCopiedFiles(array $copiedFiles): void
    {
        foreach ($copiedFiles as $file) {
            Storage::disk($file['disk'])->delete($file['path']);
        }
    }
}
