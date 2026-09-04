<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
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
    'requester_id',
    'start_date',
    'end_date',
    'completion_date',
    'start_time',
    'end_time',
    'estimated_minutes',
    'is_blocked',
    'requires_closure_feedback',
    'closure_feedback',
])]
class Task extends BaseModel
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, LogsModelActivity;

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
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
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
     * The sub-tasks hanging off this one. Depth is NOT limited (D-12): no
     * limit was requested and inventing one would be a business rule.
     *
     * @return HasMany<Task, $this>
     */
    public function subtasks(): HasMany
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
     * The users following the Task without being accountable for it. The
     * same user may be both an assignee and a watcher (AC-083).
     *
     * @return BelongsToMany<User, $this>
     */
    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_watcher')->orderBy('users.name');
    }
}
