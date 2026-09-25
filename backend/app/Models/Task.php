<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasNotes;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Task entity (spec 0101): a trackable, recursively hierarchical activity,
 * linkable to Anagrafica (Registry), Referente, Opportunita' and Commessa.
 *
 * `creator_id` is DELIBERATELY absent from #[Fillable] (D-10): the Service
 * sets it from the authenticated actor at create time and it is immutable
 * afterwards — UpdateTaskRequest rejects it with `prohibited`, this model
 * simply cannot receive it by mass assignment.
 *
 * There is NO `completion_percentage` attribute: the percentage is a
 * PROJECTION of `taskStatus->completion_percentage`, resolved at response
 * time by App\Services\Tasks\TaskStatusResolver (D-6), so a Task's progress
 * can never drift from its status. Nothing here is keyed off a status
 * LABEL — only off `system_key` (AC-024).
 *
 * Spec 0117 adds the two collaborative concerns: `HasNotes` (the thread,
 * gated per record by App\Services\Tasks\TaskNotable) and `HasAttachments`
 * (the documents, whose rows and binaries the trait's own `deleting` hook
 * cleans up when the Task is really deleted). Neither adds a column: both
 * morph relations already live on their own tables.
 *
 * `task_recurrence_id` (spec 0120, D-3) is DELIBERATELY absent from
 * #[Fillable], the same category as `creator_id`/`task_status_id`: it is
 * never client input on its own, only a consequence of the `recurrence`
 * object App\Services\Tasks\TaskRecurrenceService resolves into a row — the
 * FormRequests reject the raw key outright, and this model simply cannot
 * receive it by mass assignment either way.
 *
 * `work_order_stage_id` (spec 0146, D-3) IS fillable — genuine client input
 * on a root Task's create/update, validated upstream (must belong to
 * `work_order_id`, `prohibited` on a sub-task, 409 on a closed stage).
 * `stage_position` is DELIBERATELY absent: the board's own move/bulk
 * services assign it, the same category as `creator_id`.
 *
 * Spec 0154 adds two q-net-aligned fields: `is_private` (D-2, narrows
 * App\Services\Tasks\TaskVisibilityScope to the Task's own membership only)
 * and `lead_id` (D-4, must belong to `registry_id` when the Task carries
 * one).
 */
#[Fillable([
    'title',
    'description',
    'registry_id',
    'referent_id',
    'parent_task_id',
    'task_type_id',
    'task_status_id',
    'task_priority_id',
    'task_importance_id',
    'task_category_id',
    'opportunity_id',
    'work_order_id',
    'work_order_stage_id',
    'requester_id',
    'start_date',
    'end_date',
    'completion_date',
    'start_time',
    'end_time',
    'estimated_minutes',
    'is_blocked',
    'requires_closure_feedback',
    'requires_validation',
    'closure_feedback',
    'is_private',
    'lead_id',
])]
class Task extends BaseModel
{
    /** @use HasFactory<TaskFactory> */
    use HasAttachments, HasFactory, HasNotes, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'completion_date' => 'date:Y-m-d',
            'estimated_minutes' => 'int',
            'is_blocked' => 'boolean',
            'requires_closure_feedback' => 'boolean',
            'requires_validation' => 'boolean',
            'stage_position' => 'int',
            'subtask_position' => 'int',
            'is_private' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<TaskStatus, $this>
     */
    public function taskStatus(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class);
    }

    /**
     * @return BelongsTo<TaskType, $this>
     */
    public function taskType(): BelongsTo
    {
        return $this->belongsTo(TaskType::class);
    }

    /**
     * @return BelongsTo<TaskCategory, $this>
     */
    public function taskCategory(): BelongsTo
    {
        return $this->belongsTo(TaskCategory::class);
    }

    /**
     * @return BelongsTo<TaskPriority, $this>
     */
    public function taskPriority(): BelongsTo
    {
        return $this->belongsTo(TaskPriority::class);
    }

    /**
     * @return BelongsTo<TaskImportance, $this>
     */
    public function taskImportance(): BelongsTo
    {
        return $this->belongsTo(TaskImportance::class);
    }

    /**
     * @return BelongsTo<Registry, $this>
     */
    public function registry(): BelongsTo
    {
        return $this->belongsTo(Registry::class);
    }

    /**
     * @return BelongsTo<Referent, $this>
     */
    public function referent(): BelongsTo
    {
        return $this->belongsTo(Referent::class);
    }

    /**
     * @return BelongsTo<Opportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /**
     * The lead this Task refers to, if any (spec 0154, D-4): must belong to
     * `registry_id` when the Task also carries one
     * (App\Services\Tasks\TaskLeadRegistryGuard) — `nullOnDelete` at the
     * schema, deleting a Lead never deletes its Tasks.
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * The "Fase" this root Task sits in on its commessa's task board (spec
     * 0146, D-2/D-3), null for "Senza fase" and always null on a sub-task.
     *
     * @return BelongsTo<WorkOrderStage, $this>
     */
    public function workOrderStage(): BelongsTo
    {
        return $this->belongsTo(WorkOrderStage::class);
    }

    /**
     * The user who ASKED for the Task — a plain, nullable, client-writable
     * field, deliberately distinct from the creator (D-10).
     *
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * The user who created the Task: server-set, immutable, NOT NULL (D-10).
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * The parent this Task hangs off, if it is itself a sub-task (D-12).
     * restrictOnDelete: a parent with children cannot be removed (D-8a).
     *
     * @return BelongsTo<Task, $this>
     */
    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    /**
     * The recurrence series this Task belongs to, if any (spec 0120, D-3):
     * null for an ordinary Task, set on the capostipite AND on every
     * occurrence the scheduler materializes off it. `nullOnDelete` at the
     * schema means cancelling the series (D-10) leaves this simply null,
     * never removes the Task.
     *
     * @return BelongsTo<TaskRecurrence, $this>
     */
    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(TaskRecurrence::class, 'task_recurrence_id');
    }

    /**
     * The sub-tasks hanging off this one. Depth is NOT limited (D-12): no
     * limit was requested and inventing one would be a business rule.
     * Ordered by `subtask_position` then `id` (spec 0155, D-4): the manual
     * order the detail's panel lets the actor drag into place, with a stable
     * tie-break for the rows a batch create wrote at the same position 0
     * default before any reorder ever ran.
     *
     * `subtask_position` is DELIBERATELY absent from #[Fillable], the same
     * category as `stage_position`: only `App\Services\Tasks\TaskSubtaskBatchCreator`
     * (create) and `App\Services\Tasks\TaskSubtaskReorderService` (reorder)
     * assign it, both by direct property assignment.
     *
     * @return HasMany<Task, $this>
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id')->orderBy('subtask_position')->orderBy('id');
    }

    /**
     * The same direct children as subtasks(), kept apart because callers load
     * `subtasks` scoped to the viewer (TaskVisibilityScope) while the
     * completion percentage (spec 0153, D-10) must average EVERY child: it is
     * a property of the task, not of who looks at it.
     *
     * @return HasMany<Task, $this>
     */
    public function completionSubtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    /**
     * The users accountable for carrying the Task out. An unordered set
     * (D-8): no "n-th assignee" ranking to preserve, hence no `position`
     * pivot column and no orderByPivot.
     *
     * @return BelongsToMany<User, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignee')->orderBy('users.name');
    }

    /**
     * The users following the Task without being accountable for it.
     *
     * The two sets are DISJOINT since spec 0118 D-9: a watcher may be neither
     * the creator, nor the requester, nor an assignee, and
     * App\Services\Tasks\TaskWatcherOverlapGuard refuses the overlap 422 on
     * every write. This RETIRES AC-083 of spec 0101, which used to allow it —
     * the requirement changed by user decision, so a reader finding the old
     * claim here would be reading a rule that no longer holds. Nothing in the
     * SCHEMA enforces the disjunction (two independent pivot tables): it is an
     * application-level invariant, which is why the guard exists.
     *
     * @return BelongsToMany<User, $this>
     */
    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_watcher')->orderBy('users.name');
    }

    /**
     * The segnatempo rows logged against this Task (spec 0122). Read by the
     * task board (spec 0146) via `withSum('timeEntries', 'minutes')` for
     * `actual_minutes` — never loaded whole, the board only ever needs the
     * aggregate.
     *
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }
}
