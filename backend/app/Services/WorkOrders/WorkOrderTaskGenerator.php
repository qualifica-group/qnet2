<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use App\Models\WorkOrder;
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
 */
final class WorkOrderTaskGenerator
{
    public function __construct(
        private readonly TaskInitialStatusResolver $initialStatusResolver,
        private readonly AttachmentService $attachments,
        private readonly TaskNotifier $notifier,
    ) {}

    /**
     * @return array<int, Task>
     */
    public function generate(WorkOrder $workOrder, TaskTemplate $template, User $actor): array
    {
        $template->loadMissing(['items.attachments', 'items.taskStatus']);
        $workOrder->loadMissing('quote.opportunity');
        $supervisorIds = $workOrder->supervisors()->pluck('users.id')->all();

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
                $tasks[] = $this->materialize($item, $workOrder, $actor, $supervisorIds, $copiedFiles);
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
     * (`generated_task` shape), resolve the status (D-4), sync assignees
     * (D-2) and copy the row's own attachments (D-6).
     *
     * @param  array<int, int>  $supervisorIds
     * @param  array<int, array{disk: string, path: string}>  $copiedFiles
     */
    private function materialize(
        TaskTemplateItem $item,
        WorkOrder $workOrder,
        User $actor,
        array $supervisorIds,
        array &$copiedFiles,
    ): Task {
        // `start_date` casts to a MUTABLE Carbon (no immutable-date override
        // in this codebase): addDays() below must run on a copy, or the
        // second row generated off the same $workOrder would see its start
        // date already shifted by the first row's own offset.
        $startDate = $workOrder->start_date;
        $endDate = $startDate->copy()->addDays($item->due_offset_days);

        $task = new Task([
            'title' => $item->title,
            'description' => $item->description,
            'estimated_minutes' => $item->estimated_minutes,
            'work_order_id' => $workOrder->id,
            'registry_id' => $workOrder->quote->opportunity->registry_id,
            'opportunity_id' => $workOrder->quote->opportunity_id,
            'requester_id' => $actor->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'requires_validation' => false,
        ]);
        $task->creator_id = $actor->id;
        $task->task_status_id = $this->resolveInitialStatus($item, $supervisorIds, $actor);
        $task->save();

        $task->assignees()->sync($supervisorIds);

        foreach ($item->attachments as $attachment) {
            $copy = $this->attachments->copyTo($attachment, $task, 'documents', $actor);
            $copiedFiles[] = ['disk' => $copy->disk, 'path' => $copy->path];
        }

        return $task;
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
